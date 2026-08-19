 <?php

    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\ExpenseController;

    Route::get('/ping', function () {
        return response()->json([
            'estado' => '¡Render por fin se actualizó!',
            'datos_de_prueba' => \App\Models\Expense::all()
        ]);
    });
    Route::post('/gastos', [ExpenseController::class, 'store']);
