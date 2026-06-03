<?php

namespace App\Support;

use Illuminate\Support\Arr;

class HttpMetrics
{
    private const FILE = 'metrics/http_metrics.json';

    public static function record(string $method, string $route, int $status, float $duration): void
    {
        $path = self::path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            return;
        }

        try {
            flock($handle, LOCK_EX);

            $contents = stream_get_contents($handle);
            $data = $contents ? json_decode($contents, true) : [];

            if (! is_array($data)) {
                $data = [];
            }

            $key = self::key($method, $route, $status);
            $current = Arr::get($data, $key, [
                'method' => $method,
                'route' => $route,
                'status' => $status,
                'count' => 0,
                'duration_sum' => 0,
            ]);

            $current['count']++;
            $current['duration_sum'] += $duration;

            Arr::set($data, $key, $current);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function export(): string
    {
        $path = self::path();
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

        if (! is_array($data)) {
            $data = [];
        }

        $lines = [
            '# HELP laravel_http_requests_total Total HTTP requests handled by Laravel.',
            '# TYPE laravel_http_requests_total counter',
        ];

        foreach (self::flatten($data) as $metric) {
            $labels = self::labels($metric);
            $lines[] = sprintf('laravel_http_requests_total{%s} %d', $labels, $metric['count']);
        }

        $lines[] = '# HELP laravel_http_request_duration_seconds_sum Total request duration in seconds.';
        $lines[] = '# TYPE laravel_http_request_duration_seconds_sum counter';

        foreach (self::flatten($data) as $metric) {
            $labels = self::labels($metric);
            $lines[] = sprintf('laravel_http_request_duration_seconds_sum{%s} %.6F', $labels, $metric['duration_sum']);
        }

        $lines[] = '# HELP laravel_http_request_duration_seconds_count Total measured request durations.';
        $lines[] = '# TYPE laravel_http_request_duration_seconds_count counter';

        foreach (self::flatten($data) as $metric) {
            $labels = self::labels($metric);
            $lines[] = sprintf('laravel_http_request_duration_seconds_count{%s} %d', $labels, $metric['count']);
        }

        return implode("\n", $lines)."\n";
    }

    private static function flatten(array $data): array
    {
        $metrics = [];

        foreach ($data as $methods) {
            foreach ($methods as $routes) {
                foreach ($routes as $metric) {
                    if (is_array($metric) && isset($metric['method'], $metric['route'], $metric['status'])) {
                        $metrics[] = $metric;
                    }
                }
            }
        }

        return $metrics;
    }

    private static function key(string $method, string $route, int $status): string
    {
        return implode('.', [
            self::sanitizeKey($method),
            self::sanitizeKey($route),
            (string) $status,
        ]);
    }

    private static function labels(array $metric): string
    {
        return sprintf(
            'method="%s",route="%s",status="%s"',
            self::escapeLabel((string) $metric['method']),
            self::escapeLabel((string) $metric['route']),
            self::escapeLabel((string) $metric['status'])
        );
    }

    private static function sanitizeKey(string $value): string
    {
        return str_replace('.', '_', $value);
    }

    private static function escapeLabel(string $value): string
    {
        return addcslashes($value, "\\\"\n");
    }

    private static function path(): string
    {
        return storage_path('app/'.self::FILE);
    }
}
