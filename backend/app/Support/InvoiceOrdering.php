<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class InvoiceOrdering
{
    public static function apply(Builder|Relation $query, ?string $sort, ?string $direction, string $dateColumn = 'issued_date'): Builder|Relation
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        if ($sort === 'invoice_no') {
            $numberExpression = match (DB::connection()->getDriverName()) {
                'mysql' => "CASE WHEN invoice_no LIKE 'TKS%' THEN CAST(SUBSTRING(invoice_no, 4) AS UNSIGNED) WHEN invoice_no LIKE 'A%' OR invoice_no LIKE 'C%' THEN CAST(SUBSTRING(invoice_no, 2) AS UNSIGNED) ELSE 0 END",
                'pgsql', 'sqlite' => "CASE WHEN invoice_no LIKE 'TKS%' THEN CAST(SUBSTR(invoice_no, 4) AS INTEGER) WHEN invoice_no LIKE 'A%' OR invoice_no LIKE 'C%' THEN CAST(SUBSTR(invoice_no, 2) AS INTEGER) ELSE 0 END",
                default => "CASE WHEN invoice_no LIKE 'TKS%' THEN CAST(SUBSTRING(invoice_no, 4) AS INTEGER) WHEN invoice_no LIKE 'A%' OR invoice_no LIKE 'C%' THEN CAST(SUBSTRING(invoice_no, 2) AS INTEGER) ELSE 0 END",
            };

            $builder->reorder()->orderByRaw($numberExpression.' '.$direction)->orderBy('id', $direction);
            return $query;
        }

        if ($sort === 'issued_date') {
            $builder->reorder()->orderBy($dateColumn, $direction)->orderBy('id', $direction);
            return $query;
        }

        return $query;
    }
}
