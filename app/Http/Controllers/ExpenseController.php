<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        return response()->json(Expense::latest()->get());
    }

    public function store(Request $request)
    {
        $validated =$request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric'
        ]);

        $expense = Expense::create($validated);
        return response()->json($expense, 201);
    }
}