<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class UmkmController extends Controller {
    public function getProducts() {
        return response()->json(['data' => Product::with('shop')->get()]);
    }
}