<?php

namespace App\Http\Controllers;

use App\Events\CoordinatorMoved;
use App\Http\Requests\VisitRequest;
use Illuminate\Http\JsonResponse;

class CoordinatorController extends Controller
{
    /**
     * Kunjungan koordinator.
     *
     * Khusus koordinator. `classroom` berisi slug kelas tujuan, atau `null` untuk kembali ke ruang koordinator.
     * Posisinya disimpan, jadi `GET /classrooms/{slug}/presences` tahu koordinator mana yang sedang berkunjung.
     * Kelas asal dan tujuan menerima event `CoordinatorMoved`.
     */
    public function visit(VisitRequest $request): JsonResponse
    {
        $coordinator = $request->user();
        $from = $coordinator->visiting();
        $to = $request->validated('classroom');

        $coordinator->visit($to);

        if ($from !== $to) {
            CoordinatorMoved::dispatch($coordinator, $from, $to);
        }

        return response()->json(['data' => ['from' => $from, 'to' => $to]]);
    }
}
