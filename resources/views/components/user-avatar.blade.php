@props(['user' => null, 'class' => 'w-9 h-9', 'textSize' => 'text-xs'])

@php
    $u = $user ?? auth()->user();
    $name = $u->Username ?? $u->Name ?? $u->Full_Name ?? $u->name ?? 'User';
    $photoUrl = app(\App\Services\Core\AvatarResolver::class)->resolve($u);
@endphp

<x-resolved-avatar :name="$name" :url="$photoUrl" :class="$class" :text-size="$textSize" border-class="border-sky-400/80" loading="eager" />
