<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only administrators can view activity logs.'
        );

        $request->validate([
            'search_by' => ['sometimes', 'in:all,user,record,activity'],
        ]);

        $query = ActivityLog::query()->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $searchBy = $request->query('search_by', 'all');
            if ($searchBy === 'user') {
                $query->where(function ($builder) use ($search) {
                    $builder->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%");
                });
            } elseif ($searchBy === 'record') {
                $query->where('subject_label', 'like', "%{$search}%");
            } elseif ($searchBy === 'activity') {
                $query->where('description', 'like', "%{$search}%");
            } else {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('subject_label', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->query('subject_type'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $logs = $query->paginate(30);
        $vehicleIds = collect();
        $userIds = collect();
        $invoiceItemIds = collect();

        foreach ($logs->getCollection() as $log) {
            foreach ([$log->old_values, $log->new_values] as $values) {
                if (! is_array($values)) {
                    continue;
                }

                if (! empty($values['vehicle_id'])) {
                    $vehicleIds->push((int) $values['vehicle_id']);
                }

                foreach ($values as $key => $value) {
                    if ($key === 'items' && is_array($value)) {
                        foreach ($value as $item) {
                            if (is_array($item) && ! empty($item['id'])) {
                                $invoiceItemIds->push((int) $item['id']);
                            }
                        }
                    }

                    if (str_ends_with((string) $key, '_by') && is_numeric($value) && (int) $value > 0) {
                        $userIds->push((int) $value);
                    }
                }
            }
        }

        $vehicles = Vehicle::whereIn('id', $vehicleIds->unique())
            ->pluck('plate_number', 'id');
        $users = User::whereIn('id', $userIds->unique())
            ->pluck('name', 'id');
        $barrelsByInvoiceItem = InvoiceItem::query()
            ->with('barrel:id,code')
            ->whereIn('id', $invoiceItemIds->unique())
            ->get(['id', 'barrel_id'])
            ->mapWithKeys(fn ($item) => [$item->id => $item->barrel?->code])
            ->all();

        $logs->setCollection(
            $logs->getCollection()->map(function ($log) use ($vehicles, $users, $barrelsByInvoiceItem) {
                $log->old_values = $this->describeReferences(
                    $log->old_values,
                    $vehicles,
                    $users,
                    $barrelsByInvoiceItem
                );
                $log->new_values = $this->describeReferences(
                    $log->new_values,
                    $vehicles,
                    $users,
                    $barrelsByInvoiceItem
                );

                return $log;
            })
        );

        return response()->json($logs);
    }

    private function describeReferences(
        ?array $values,
        $vehicles,
        $users,
        array $barrelsByInvoiceItem
    ): ?array {
        if ($values === null) {
            return null;
        }

        if (array_key_exists('vehicle_id', $values)) {
            $id = $values['vehicle_id'];
            $values['vehicle'] = $id
                ? (($vehicles[$id] ?? 'Unknown vehicle').' (ID: '.$id.')')
                : null;
            unset($values['vehicle_id']);
        }

        if (isset($values['items']) && is_array($values['items'])) {
            $values['items'] = array_map(function ($item) use ($barrelsByInvoiceItem) {
                if (! is_array($item)) {
                    return $item;
                }

                if (empty($item['barrel']) && ! empty($item['id'])) {
                    $item['barrel'] = $barrelsByInvoiceItem[(int) $item['id']] ?? 'Unknown';
                }

                unset($item['id']);

                return $item;
            }, $values['items']);
        }

        foreach ($values as $key => $value) {
            if (str_ends_with((string) $key, '_by') && is_numeric($value)) {
                $values[$key] = $users[(int) $value] ?? 'Unknown user';
            }
        }

        return $values;
    }
}
