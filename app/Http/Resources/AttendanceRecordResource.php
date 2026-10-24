<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * 勤怠情報をAPIレスポンス用の配列に変換する。
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'date' => $this->date,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'comment' => $this->comment,
            'total_time' => $this->total_time,
            'total_break_time' => $this->total_break_time,

            'user' => UserResource::make(
                $this->whenLoaded('user')
            ),

            'breaks' => BreakTimeResource::collection(
                $this->whenLoaded('breakTimes')
            ),

            'applications' => AttendanceCorrectionRequestResource::collection(
                $this->whenLoaded('attendanceCorrectionRequests')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
