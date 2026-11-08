<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppRuleRequest;
use App\Http\Resources\AppRuleResource;
use App\Models\AppRule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

// Aturan pencocokan aplikasi untuk agent. Perubahan terpakai saat agent mengambil GET /agent/config berikutnya.
class AppRuleController extends Controller
{
    /**
     * Daftar aturan aplikasi.
     *
     * Khusus koordinator. Termasuk yang nonaktif, urut prioritas tertinggi dulu.
     */
    public function index(): AnonymousResourceCollection
    {
        return AppRuleResource::collection(AppRule::orderByDesc('priority')->orderBy('id')->get());
    }

    /**
     * Tambah aturan aplikasi.
     *
     * Khusus koordinator. `process` mencocokkan nama proses persis (tanpa beda huruf besar-kecil, di Windows
     * termasuk `.exe`), `title` mencocokkan judul window yang mengandung `pattern`. `os` kosong = semua OS.
     * Kalau beberapa aturan cocok, `priority` terbesar menang.
     */
    public function store(AppRuleRequest $request): AppRuleResource
    {
        /** @status 201 */
        return new AppRuleResource(AppRule::create(['is_active' => true, ...$request->validated()]));
    }

    /**
     * Ubah atau nonaktifkan aturan aplikasi.
     *
     * Khusus koordinator. Field boleh dikirim sebagian, misalnya `{"is_active": false}`.
     */
    public function update(AppRuleRequest $request, AppRule $appRule): AppRuleResource
    {
        $appRule->update($request->validated());

        return new AppRuleResource($appRule);
    }

    /**
     * Hapus aturan aplikasi.
     *
     * Khusus koordinator. Untuk mematikan sementara, lebih baik `PATCH` dengan `is_active: false`.
     */
    public function destroy(AppRule $appRule): Response
    {
        $appRule->delete();

        return response()->noContent();
    }
}
