<?php
$sidebarCurrentPage = basename($_SERVER['PHP_SELF']);
?>

<div x-data="{ collapsed: localStorage.getItem('sidebarCollapsed') === 'true' }"
     x-init="$watch('collapsed', val => localStorage.setItem('sidebarCollapsed', val))"
     class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside :class="collapsed ? 'w-16' : 'w-64'"
           class="fixed top-0 left-0 h-screen bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 flex flex-col z-40 transition-all duration-300 overflow-hidden">

        <!-- Logo / Brand + Toggle Button -->
        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
             :class="collapsed ? 'justify-center px-0 py-5' : 'justify-between px-4 py-5'">

            <!-- Logo (hidden when collapsed) -->
            <div x-show="!collapsed" x-transition.opacity class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0
                            01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622
                            5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div class="overflow-hidden">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white leading-tight whitespace-nowrap">Lost & Found</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">Admin Panel</div>
                </div>
            </div>

            <!-- Toggle Button -->
            <button @click="collapsed = !collapsed"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition flex-shrink-0"
                    :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <!-- Collapse icon (show when expanded) -->
                <svg x-show="!collapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
                <!-- Expand icon (show when collapsed) -->
                <svg x-show="collapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden px-2 py-4 space-y-1">

            <?php
            $navItems = [
                ['href' => BASE_URL . 'admin/index.php',  'label' => 'Dashboard', 'file' => 'index.php',  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
                ['href' => BASE_URL . 'admin/items.php',  'label' => 'Items',     'file' => 'items.php',  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>'],
                ['href' => BASE_URL . 'admin/users.php',  'label' => 'Users',     'file' => 'users.php',  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                ['href' => BASE_URL . 'admin/claims.php', 'label' => 'Claims',    'file' => 'claims.php', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'],
                ['href' => BASE_URL . 'admin/logs.php',   'label' => 'Logs',      'file' => 'logs.php',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
            ];

            foreach ($navItems as $item):
                $isActive = ($sidebarCurrentPage === $item['file']);
                $activeClasses   = 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium';
                $inactiveClasses = 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white';
            ?>
                <a href="<?= $item['href'] ?>"
                   class="flex items-center gap-3 rounded-lg text-sm transition <?= $isActive ? $activeClasses : $inactiveClasses ?>"
                   :class="collapsed ? 'justify-center px-0 py-2.5' : 'px-3 py-2.5'"
                   :title="collapsed ? '<?= $item['label'] ?>' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?= $item['icon'] ?>
                    </svg>
                    <span x-show="!collapsed" x-transition.opacity class="whitespace-nowrap">
                        <?= $item['label'] ?>
                    </span>
                    <?php if ($item['file'] === 'claims.php' && isset($totalPendingClaims) && $totalPendingClaims > 0): ?>
                        <span x-show="!collapsed" x-transition.opacity
                              class="ml-auto bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap">
                            <?= $totalPendingClaims ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

        </nav>

        <!-- Bottom: View Site + Logout -->
        <div class="px-2 py-4 border-t border-gray-200 dark:border-gray-700 space-y-1">

            <a href="<?= BASE_URL ?>pages/home.php"
               class="flex items-center gap-3 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition"
               :class="collapsed ? 'justify-center px-0 py-2.5' : 'px-3 py-2.5'"
               title="View Site">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                <span x-show="!collapsed" x-transition.opacity class="whitespace-nowrap">View Site</span>
            </a>

            <a href="<?= BASE_URL ?>auth/logout.php"
               class="flex items-center gap-3 rounded-lg text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition"
               :class="collapsed ? 'justify-center px-0 py-2.5' : 'px-3 py-2.5'"
               title="Logout">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span x-show="!collapsed" x-transition.opacity class="whitespace-nowrap">Logout</span>
            </a>

        </div>

    </aside>

    <!-- CONTENT WRAPPER — shifts with sidebar -->
    <div :class="collapsed ? 'ml-16' : 'ml-64'"
         class="flex-1 flex flex-col min-h-screen bg-gray-50 dark:bg-gray-900 transition-all duration-300">
         