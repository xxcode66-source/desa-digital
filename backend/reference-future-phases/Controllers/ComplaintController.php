<?php
namespace App\Http\Controllers\Api;

use App\Http/Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller {
    public function submit(Request $request) {
        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'menunggu'
        ]);
        return response()->json(['message' => 'Laporan Diterima', 'data' => $complaint], 201);
    }
}