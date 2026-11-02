<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Track Atlas</title>
    <style>
    body{font:15px system-ui,sans-serif;background:#faf9f6;color:#252525;margin:0}main{max-width:1120px;margin:36px auto;padding:0 20px}
    header{margin-bottom:26px}h1{margin:0;font-size:32px}small,.muted{color:#717171}
    .layout{display:grid;grid-template-columns:260px 1fr;gap:24px}.panel{background:white;border:1px solid #e8e5de;border-radius:12px;padding:20px}
    label{display:block;margin:10px 0}input[type=search],input[type=text],select{padding:9px;border:1px solid #ccc;border-radius:7px;max-width:100%;box-sizing:border-box}
    button{border:0;border-radius:7px;background:#373630;color:white;padding:9px 14px;cursor:pointer}.track{padding:15px 0;border-bottom:1px solid #eee}
    .tags{font-size:12px;color:#646464}.edit{margin-top:10px}summary{cursor:pointer}.pagination{padding:20px 0}
    @media(max-width:700px){.layout{grid-template-columns:1fr}}
    </style>
</head>
<body><main>
<header><h1>Track Atlas</h1><small>Локальный каталог музыкальных референсов</small></header>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<div class="layout">
<aside class="panel">
<form method="get" action="{{ route('tracks.index') }}">
<label>Поиск <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Название или исполнитель"></label>
@foreach(['genres' => 'genre', 'moods' => 'mood'] as $param => $type)
<h3>{{ $type === 'genre' ? 'Жанры' : 'Настроения / Vibe' }}</h3>
@forelse($tags->get($type, collect()) as $tag)
<label><input type="checkbox" name="{{ $param }}[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $filters[$param] ?? []))> {{ $tag->name }}</label>
@empty <p class="muted">Категорий пока нет</p> @endforelse
@endforeach
<button type="submit">Найти</button> <a href="{{ route('tracks.index') }}">Сбросить</a>
</form>
<hr>
<form method="post" action="{{ route('tags.store') }}">@csrf
<h3>Новая категория</h3><select name="type"><option value="genre">Жанр</option><option value="mood">Настроение</option></select>
<input type="text" name="name" required maxlength="80" placeholder="Название">
<button>Добавить</button>
</form>
</aside>
<section class="panel">
<h2>Треки ({{ $tracks->total() }})</h2>
@forelse($tracks as $track)
<div class="track">
<strong>{{ $track->artist ? $track->artist.' — ' : '' }}{{ $track->title }}</strong>
<div class="tags">{{ $track->tags->pluck('name')->implode(' · ') ?: 'Без категорий' }}</div>
<details class="edit"><summary>Изменить категории</summary>
<form method="post" action="{{ route('tracks.tags.update', $track) }}">@csrf @method('PUT')
@foreach($tags as $type => $items)
<p><strong>{{ $type === 'genre' ? 'Жанры' : 'Настроения' }}</strong></p>
@foreach($items as $tag)
<label><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked($track->tags->contains($tag->id))> {{ $tag->name }}</label>
@endforeach
@endforeach
<input type="hidden" name="tags" value="" disabled>
<button type="submit">Сохранить</button>
</form>
</details></div>
@empty <p class="muted">Нет треков. Сначала выполните php artisan music:scan.</p> @endforelse
<div class="pagination">{{ $tracks->links() }}</div>
</section>
</div></main></body></html>
