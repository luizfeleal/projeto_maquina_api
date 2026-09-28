-- =====================================================================
-- Limpeza de duplicidades em extrato_maquina — SOMENTE cartão PagBank
--
-- Contexto: o webhook do PagBank (app/Http/Controllers/Pagbank/Webhooks/
-- WebhookController.php) não tinha nenhuma checagem de idempotência —
-- toda notificação reenviada pelo PagBank (retry por timeout, notificação
-- duplicada, etc.) inseria outro par de linhas Cartão/Taxa no extrato e
-- liberava a jogada de novo. Corrigido no commit da (fix(pagbank):
-- idempotência e lock no webhook para evitar extrato duplicado).
--
-- Este script só limpa o que já foi duplicado ANTES da correção. Depois
-- de aplicado o fix do webhook, o problema não deve mais se repetir — se
-- voltar a acontecer, é outra causa e não deve reusar este script sem
-- investigar de novo.
--
-- Escopo: extrato_operacao_tipo IN ('Cartão', 'Taxa') — mas restrito aos
-- id_end_to_end que têm alguma linha 'Cartão' (não 'Cartão MP', que é
-- Mercado Pago e já tem proteção própria). PIX (Efí) e Dinheiro não são
-- tocados.
--
-- Critério de duplicidade: mesma id_maquina + id_end_to_end +
-- extrato_operacao + extrato_operacao_tipo + extrato_operacao_valor.
-- Mantém a linha com o menor id_extrato_maquina (a mais antiga) de cada
-- grupo e remove as repetições.
--
-- IMPORTANTE: rode os passos NESTA ORDEM, um de cada vez, e confira o
-- resultado de cada um antes de seguir para o próximo.
-- =====================================================================


-- PASSO 0: índice necessário para o JOIN por id_end_to_end não fazer
-- scan completo da tabela (sem isso as queries abaixo podem travar).
-- Se já existir, o MySQL vai acusar "Duplicate key name" — pode ignorar.
CREATE INDEX idx_extrato_maquina_end_to_end ON extrato_maquina (id_end_to_end);


-- PASSO 0.1: materializa os id_end_to_end que são de cartão PagBank
-- (usado pelos passos seguintes para restringir o escopo). Deliberadamente
-- só 'Cartão' — 'Cartão MP' é Mercado Pago, fora do escopo desta limpeza.
DROP TEMPORARY TABLE IF EXISTS pagbank_cartao_ids;
CREATE TEMPORARY TABLE pagbank_cartao_ids AS
SELECT DISTINCT id_end_to_end
FROM extrato_maquina
WHERE extrato_operacao_tipo = 'Cartão';

CREATE INDEX idx_pagbank_cartao_ids ON pagbank_cartao_ids (id_end_to_end);

-- Confira quantos ids de cartão PagBank foram encontrados:
SELECT COUNT(*) AS total_ids_cartao_pagbank FROM pagbank_cartao_ids;


-- PASSO 1: preview - quantas linhas seriam removidas, por tipo de operação
SELECT
    t1.extrato_operacao_tipo,
    COUNT(*) AS linhas_duplicadas_a_remover
FROM extrato_maquina t1
INNER JOIN extrato_maquina t2
    ON t1.id_maquina            = t2.id_maquina
   AND t1.id_end_to_end         = t2.id_end_to_end
   AND t1.extrato_operacao      = t2.extrato_operacao
   AND t1.extrato_operacao_tipo = t2.extrato_operacao_tipo
   AND t1.extrato_operacao_valor <=> t2.extrato_operacao_valor
   AND t1.id_extrato_maquina    > t2.id_extrato_maquina
INNER JOIN pagbank_cartao_ids c
    ON c.id_end_to_end = t1.id_end_to_end
GROUP BY t1.extrato_operacao_tipo;


-- PASSO 1.1: amostra - 10 primeiros pares (linha que fica x linha que
-- será removida), lado a lado, só para conferir visualmente antes de
-- apagar qualquer coisa.
SELECT
    t2.id_extrato_maquina    AS id_que_fica,
    t2.extrato_operacao_tipo AS tipo_que_fica,
    t2.extrato_operacao_valor AS valor_que_fica,
    t2.data_criacao          AS data_que_fica,
    t1.id_extrato_maquina    AS id_que_sera_removido,
    t1.extrato_operacao_tipo AS tipo_que_sera_removido,
    t1.extrato_operacao_valor AS valor_que_sera_removido,
    t1.data_criacao          AS data_que_sera_removido,
    t1.id_end_to_end
FROM extrato_maquina t1
INNER JOIN extrato_maquina t2
    ON t1.id_maquina            = t2.id_maquina
   AND t1.id_end_to_end         = t2.id_end_to_end
   AND t1.extrato_operacao      = t2.extrato_operacao
   AND t1.extrato_operacao_tipo = t2.extrato_operacao_tipo
   AND t1.extrato_operacao_valor <=> t2.extrato_operacao_valor
   AND t1.id_extrato_maquina    > t2.id_extrato_maquina
INNER JOIN pagbank_cartao_ids c
    ON c.id_end_to_end = t1.id_end_to_end
ORDER BY t1.id_end_to_end, t1.id_extrato_maquina
LIMIT 10;


-- PASSO 2: backup das linhas que serão apagadas (por segurança, antes de
-- rodar o DELETE de verdade). Nome com data para não colidir com o
-- backup de uma limpeza anterior (extrato_maquina_duplicados_backup).
DROP TABLE IF EXISTS extrato_maquina_duplicados_pagbank_20260928_backup;
CREATE TABLE extrato_maquina_duplicados_pagbank_20260928_backup AS
SELECT t1.*
FROM extrato_maquina t1
INNER JOIN extrato_maquina t2
    ON t1.id_maquina            = t2.id_maquina
   AND t1.id_end_to_end         = t2.id_end_to_end
   AND t1.extrato_operacao      = t2.extrato_operacao
   AND t1.extrato_operacao_tipo = t2.extrato_operacao_tipo
   AND t1.extrato_operacao_valor <=> t2.extrato_operacao_valor
   AND t1.id_extrato_maquina    > t2.id_extrato_maquina
INNER JOIN pagbank_cartao_ids c
    ON c.id_end_to_end = t1.id_end_to_end;

-- Confira se a contagem bate com o total do PASSO 1:
SELECT COUNT(*) AS total_no_backup FROM extrato_maquina_duplicados_pagbank_20260928_backup;


-- PASSO 3: apaga de fato as duplicatas de cartão PagBank, mantendo a
-- linha mais antiga de cada grupo.
DELETE t1 FROM extrato_maquina t1
INNER JOIN extrato_maquina t2
    ON t1.id_maquina            = t2.id_maquina
   AND t1.id_end_to_end         = t2.id_end_to_end
   AND t1.extrato_operacao      = t2.extrato_operacao
   AND t1.extrato_operacao_tipo = t2.extrato_operacao_tipo
   AND t1.extrato_operacao_valor <=> t2.extrato_operacao_valor
   AND t1.id_extrato_maquina    > t2.id_extrato_maquina
INNER JOIN pagbank_cartao_ids c
    ON c.id_end_to_end = t1.id_end_to_end;


-- PASSO 4: confirma que não sobrou nenhuma duplicidade de cartão PagBank
-- (deve retornar 0 linhas)
SELECT
    t1.id_maquina, t1.id_end_to_end, t1.extrato_operacao, t1.extrato_operacao_tipo, t1.extrato_operacao_valor,
    COUNT(*) AS qtd
FROM extrato_maquina t1
INNER JOIN pagbank_cartao_ids c ON c.id_end_to_end = t1.id_end_to_end
GROUP BY t1.id_maquina, t1.id_end_to_end, t1.extrato_operacao, t1.extrato_operacao_tipo, t1.extrato_operacao_valor
HAVING COUNT(*) > 1;


-- PASSO 5 (opcional, só depois de validar tudo): remove a tabela de backup
-- DROP TABLE extrato_maquina_duplicados_pagbank_20260928_backup;
