<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autentificare administrare · eMUD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-stone-950 p-6 text-stone-950">
<form method="post" action="{{ route('admin.login.store') }}" class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
    @csrf
    <div class="mb-8 text-2xl font-black tracking-[.18em]">eMUD <span class="text-sm font-medium tracking-normal text-stone-500">Admin</span></div>
    <label class="mb-5 block text-sm font-medium">Email
        <input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-2 w-full rounded-lg border border-stone-300 px-3 py-2.5">
        @error('email') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="mb-5 block text-sm font-medium">Parolă
        <input name="password" type="password" required class="mt-2 w-full rounded-lg border border-stone-300 px-3 py-2.5">
    </label>
    <label class="mb-6 flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Păstrează sesiunea</label>
    <button class="w-full rounded-lg bg-lime-400 px-4 py-3 font-bold hover:bg-lime-300">Autentificare</button>
</form>
</body>
</html>
