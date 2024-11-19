<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clientes extends Model
{
    use HasFactory;
    protected $table = "clientes";
    protected $primaryKey = "id_cliente";
    public $timestamps = false;
    protected $fillable = [
        "cliente_nome",
        "cliente_celular",
        "cliente_email",
        "cliente_data_nascimento",
        "cliente_cpf_cnpj",
        "cliente_logradouro",
        "cliente_bairro",
        "cliente_cidade",
        "cliente_uf",
        "cliente_cep",
        "cliente_numero",
        "cliente_complemento",
        "checkbox_efi",
        "checkbox_pagbank"
    ];
     public static function rules($id = null)
     {
        return [
            "cliente_nome" => "required|string|max:255",
            "cliente_celular"=> "required|string|max:15",
            "cliente_data_nascimento"=> "required|string|max:25",
            "cliente_email"=> "required|string|max:255",
            "cliente_cpf_cnpj"=>"required|max:20|unique:clientes",
            "cliente_logradouro" => "required|string|max:100",
            "cliente_bairro" => "required|string|max:100",
            "cliente_cidade" => "required|string|max:100",
            "cliente_uf" => "required|string|max:2",
            "cliente_cep" => "required|string|max:10",
            "cliente_numero" => "required|string|max:10"
        ];
     }

     public static function feedback($id = null)
     {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'unique' => 'O valor informado para o campo :attribute já está em uso.',
            'min' => 'O campo :attribute deve ter no mínimo :min caracteres.',
        ];
     }
}
