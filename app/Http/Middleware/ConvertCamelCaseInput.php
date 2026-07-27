<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ConvertCamelCaseInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->replace($this->snakeKeys($request->all()));

        return $next($request);
    }

    /**
     * @param  array<mixed>  $input
     * @return array<mixed>
     */
    private function snakeKeys(array $input): array
    {
        $payload = [];

        foreach ($input as $key => $value) {
            $normalizedKey = is_string($key) ? Str::snake($key) : $key;

            if (is_array($value)) {
                $value = $this->snakeKeys($value);
            }

            $payload[$normalizedKey] = $value;
        }

        return $payload;
    }
}
