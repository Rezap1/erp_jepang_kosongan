@extends('layouts.app')
@section('header', 'Buat Pengumuman')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <x-page-header title="Buat Pengumuman Baru" description="Publikasikan informasi kepada audiens yang tepat." :breadcrumbs="['Dashboard' => route('dashboard'), 'Pengumuman' => route('announcements.index'), 'Buat Baru' => '#']" />
    <form action="{{ route('announcements.store') }}" method="POST" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" data-submit-lock>@csrf
        @include('academic.announcements._form-fields')
        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 px-5 py-5 sm:flex-row sm:justify-end sm:px-8 lg:px-12"><x-button as="a" href="{{ route('announcements.index') }}" variant="secondary">Batal</x-button><x-button type="submit" variant="primary">Publikasikan</x-button></div>
    </form>
</div>
@endsection
