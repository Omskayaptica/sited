<?php
function render_header(): void {
    $role = $_SESSION['role'] ?? 'guest';
    $name = $_SESSION['full_name'] ?? 'Гость';
    ?>
    <header class="sticky top-0 z-50 mb-5 bg-white shadow shadow-black/10">
        <nav class="mx-auto flex h-auto max-w-7xl flex-col gap-3 px-4 py-3 sm:h-[70px] sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div class="flex items-center justify-between">
                <a href="index.php" class="inline-flex items-center gap-2 text-lg font-bold text-slate-800 no-underline">
                    <span class="text-2xl leading-none">🏠</span>
                    <span class="leading-none">ТСЖ "Наш Дом"</span>
                </a>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                <?php if ($role !== 'guest'): ?>
                    <a href="index.php" class="rounded-md px-3 py-2 text-slate-600 font-medium transition hover:bg-slate-100 hover:text-blue-600">Главная</a>

                    <?php if ($role === 'admin'): ?>
                        <a href="admin-readings.php" class="rounded-md px-3 py-2 text-amber-700 font-medium transition hover:bg-amber-50 hover:text-amber-800">📊 Все показания</a>
                        <a href="admin-requests.php" class="rounded-md px-3 py-2 text-amber-700 font-medium transition hover:bg-amber-50 hover:text-amber-800">📋 Все заявки</a>
                    <?php else: ?>
                        <a href="meter-submit.php" class="rounded-md px-3 py-2 text-slate-600 font-medium transition hover:bg-slate-100 hover:text-blue-600">⚡ Сдать показания</a>
                        <a href="my-requests.php" class="rounded-md px-3 py-2 text-slate-600 font-medium transition hover:bg-slate-100 hover:text-blue-600">📩 Мои заявки</a>
                        <a href="profile.php" class="rounded-md px-3 py-2 text-slate-600 font-medium transition hover:bg-slate-100 hover:text-blue-600">👤 Профиль</a>
                    <?php endif; ?>

                    <div class="mt-2 flex flex-col gap-2 border-t border-slate-200 pt-3 sm:mt-0 sm:flex-row sm:items-center sm:gap-3 sm:border-t-0 sm:border-l sm:pl-4 sm:pt-0">
                        <span class="text-sm font-semibold text-slate-500"><?= htmlspecialchars($name) ?></span>
                        <a href="logout.php" class="inline-flex items-center justify-center rounded-md border border-red-600 px-3 py-1.5 text-sm font-semibold text-red-600 transition hover:bg-red-600 hover:text-white">Выйти</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="rounded-md px-3 py-2 text-slate-600 font-medium transition hover:bg-slate-100 hover:text-blue-600">Войти</a>
                    <a href="register.php" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-white font-semibold transition hover:bg-blue-700">Регистрация</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <?php
}