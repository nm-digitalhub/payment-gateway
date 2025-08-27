@extends('payment-gateway::layouts.client')

@section('title', 'דשבורד לקוח')

@section('content')
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900">
        <h1 class="text-2xl font-bold mb-6">שלום, {{ auth()->user()->name }}!</h1>
        
        <!-- סטטיסטיקות כלליות -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ $stats['total_payments'] }}</div>
                <div class="text-sm text-gray-600">סה״כ תשלומים</div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ $stats['successful_payments'] }}</div>
                <div class="text-sm text-gray-600">תשלומים מוצלחים</div>
            </div>
            
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-purple-600">₪{{ number_format($stats['total_spent'], 2) }}</div>
                <div class="text-sm text-gray-600">סכום כולל</div>
            </div>
            
            <div class="bg-orange-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-orange-600">
                    {{ $stats['total_payments'] - $stats['successful_payments'] }}
                </div>
                <div class="text-sm text-gray-600">תשלומים שנכשלו</div>
            </div>
        </div>
        
        <!-- פעולות מהירות -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4">פעולות מהירות</h2>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('account.payments') }}" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    צפייה בתשלומים
                </a>
                
                <a href="{{ route('account.orders') }}" 
                   class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    היסטוריית הזמנות
                </a>
                
                <a href="{{ route('account.payment-methods') }}" 
                   class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                    אמצעי תשלום
                </a>
                
                <a href="{{ route('account.profile') }}" 
                   class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    עריכת פרופיל
                </a>
            </div>
        </div>
        
        <!-- תשלומים אחרונים -->
        @if($stats['recent_transactions']->isNotEmpty())
        <div>
            <h2 class="text-xl font-semibold mb-4">תשלומים אחרונים</h2>
            <div class="bg-gray-50 rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr class="text-right">
                            <th class="px-4 py-3">מס״ עסקה</th>
                            <th class="px-4 py-3">סכום</th>
                            <th class="px-4 py-3">סטטוס</th>
                            <th class="px-4 py-3">תאריך</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['recent_transactions'] as $transaction)
                        <tr class="border-t">
                            <td class="px-4 py-3 font-mono text-sm">
                                {{ substr($transaction->transaction_id, 0, 12) }}...
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">₪{{ number_format($transaction->amount, 2) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs
                                    @if($transaction->status === 'success') bg-green-100 text-green-800
                                    @elseif($transaction->status === 'failed') bg-red-100 text-red-800  
                                    @elseif($transaction->status === 'pending') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    @switch($transaction->status)
                                        @case('success') הושלם @break
                                        @case('failed') נכשל @break
                                        @case('pending') בתהליך @break
                                        @default {{ $transaction->status }}
                                    @endswitch
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $transaction->created_at->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 text-center">
                <a href="{{ route('account.payments') }}" 
                   class="text-blue-600 hover:text-blue-800">
                    צפייה בכל התשלומים ←
                </a>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
