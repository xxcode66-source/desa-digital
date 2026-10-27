<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use Illuminate\Http\Request;

class LetterController extends Controller {
    public function submit(Request $request) {
        $letter = Letter::create([
            'user_id' => $request->user()->id,
            'letter_type_id' => $request->letter_type_id,
            'data_json' => $request->data_json,
            'status' => 'diajukan'
        ]);
        return response()->json(['message' => 'Surat Terkirim', 'data' => $letter], 201);
    }
}