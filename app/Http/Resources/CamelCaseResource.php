<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Str;

abstract class CamelCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function payload(Request $request): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->toCamel($this->payload($request));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function toCamel(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value instanceof MissingValue) {
                continue;
            }

            if ($value instanceof JsonResource) {
                if ($value->resource instanceof MissingValue) {
                    continue;
                }

                $value = $value->resolve();
            }

            $camelKey = is_string($key) ? Str::camel($key) : $key;

            if (is_array($value)) {
                $value = $this->toCamelArray($value);
            }

            $result[$camelKey] = $value;
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function toCamelArray(array $data): array
    {
        $isList = array_is_list($data);
        $result = [];

        foreach ($data as $key => $value) {
            if ($value instanceof MissingValue) {
                continue;
            }

            if ($value instanceof JsonResource) {
                if ($value->resource instanceof MissingValue) {
                    continue;
                }
                $value = $value->resolve();
            }

            if (is_array($value)) {
                $value = $this->toCamelArray($value);
            }

            if ($isList) {
                $result[] = $value;
            } else {
                $result[is_string($key) ? Str::camel($key) : $key] = $value;
            }
        }

        return $result;
    }
}
