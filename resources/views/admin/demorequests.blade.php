@extends('admin.layout')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th class="px-4 py-3">Entreprise</th>
                    <th class="px-4 py-3">Contact</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $request)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3">{{ $request['company'] }}</td>
                        <td class="px-4 py-3">{{ $request['name'] }}</td>
                        <td class="px-4 py-3">{{ $request['email'] }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-700">{{ $request['status'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
