<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;

class AttendanceRecordController extends Controller
{
    /**
     * 勤怠一覧を取得する。
     */
    public function index(IndexAttendanceRecordRequest $request)
    {
        $validated = $request->validated();

        $perPage = $validated['per_page'] ?? 20;

        $attendances = Attendance::with(['user', 'breakTimes'])
            ->when(
                isset($validated['user_id']),
                fn ($query) => $query->where(
                    'user_id',
                    $validated['user_id']
                )
            )
            ->when(
                isset($validated['date']),
                fn ($query) => $query->where(
                    'date',
                    $validated['date']
                )
            )
            ->when(
                isset($validated['month']),
                fn ($query) => $query
                    ->whereYear('date', substr($validated['month'], 0, 4))
                    ->whereMonth('date', substr($validated['month'], 5, 2))
            )
            ->latest('date')
            ->paginate($perPage);

        return AttendanceRecordResource::collection($attendances);
    }

    /**
     * 勤怠情報を登録する。
     */
    public function store(StoreAttendanceRecordRequest $request)
    {
        $attendance = $request->user()
            ->attendances()
            ->create($request->validated());

        return (new AttendanceRecordResource(
            $attendance->load([
                'user',
                'breakTimes',
                'attendanceCorrectionRequests',
            ])
        ))->response()->setStatusCode(201);
    }

    /**
     * 指定された勤怠情報を取得する。
     */
    public function show(string $id): AttendanceRecordResource
    {
        $attendance = Attendance::with([
            'user',
            'breakTimes',
            'attendanceCorrectionRequests',
        ])->findOrFail($id);

        return new AttendanceRecordResource($attendance);
    }

    /**
     * 勤怠情報を更新する。
     */
    public function update(
        UpdateAttendanceRecordRequest $request,
        string $id
    ): AttendanceRecordResource {
        $attendance = Attendance::findOrFail($id);

        $this->authorize('update', $attendance);

        $attendance->update($request->validated());

        $attendance->load([
            'user',
            'breakTimes',
            'attendanceCorrectionRequests',
        ]);

        return new AttendanceRecordResource($attendance);
    }

    /**
     * 勤怠情報を削除する。
     */
    public function destroy(string $id)
    {
        $attendance = Attendance::findOrFail($id);

        $this->authorize('delete', $attendance);

        $attendance->delete();

        return response()->noContent();
    }
}
