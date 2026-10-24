<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreakTimeResource extends JsonResource
{
    /**
     * 休憩情報をAPIレスポンス用の配列に変換する。
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_id' => $this->attendance_id,
            'break_in' => $this->break_in,
            'break_out' => $this->break_out,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
