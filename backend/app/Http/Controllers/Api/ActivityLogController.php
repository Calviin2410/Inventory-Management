<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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

        $query = ActivityLog::query()->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('actor_name', 'like', "%{$search}%")
                    ->orWhere('actor_email', 'like', "%{$search}%")
                    ->orWhere('subject_label', 'like', "%{$search}%");
            });
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

        foreach ($logs->getCollection() as $log) {
            foreach ([$log->old_values, $log->new_values] as $values) {
                if (! is_array($values)) {
                    continue;
                }

                if (! empty($values['vehicle_id'])) {
                    $vehicleIds->push((int) $values['vehicle_id']);
                }
            }
        }

        $vehicles = Vehicle::whereIn('id', $vehicleIds->unique())
            ->pluck('plate_number', 'id');

        $logs->setCollection(
            $logs->getCollection()->map(function ($log) use ($vehicles) {
                $log->old_values = $this->describeReferences(
                    $log->old_values,
                    $vehicles
                );
                $log->new_values = $this->describeReferences(
                    $log->new_values,
                    $vehicles
                );

                return $log;
            })
        );

        return response()->json($logs);
    }

    private function describeReferences(
        ?array $values,
        $vehicles
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

        return $values;
    }
}
