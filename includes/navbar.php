<nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <a href="<?php echo BASE_URL; ?>pages/home.php"
               class="text-xl font-semibold text-blue-600 dark:text-blue-400 tracking-tight">
                Lost &amp; Found
            </a>

            <div class="hidden md:flex items-center gap-8">
                <a href="<?php echo BASE_URL; ?>pages/home.php"
                   class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400'; ?> transition">
                   Home
                </a>
                <a href="<?php echo BASE_URL; ?>pages/report.php"
                   class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400'; ?> transition">
                   Report Item
                </a>
                <a href="<?php echo BASE_URL; ?>pages/search.php"
                   class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400'; ?> transition">
                   Search
                </a>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo BASE_URL; ?>pages/my_items.php"
                       class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'my_items.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400'; ?> transition">
                       My Items
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="<?php echo BASE_URL; ?>admin/index.php"
                           class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition">
                           Admin Panel
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="w-px h-5 bg-gray-200 dark:bg-gray-600"></div>

                <button @click="dark = !dark"
                        x-data
                        class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
                    </svg>
                    <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707.707M6.343 6.343l-.707.707M12 7a5 5 0 100 10A5 5 0 0012 7z"/>
                    </svg>
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo BASE_URL; ?>auth/logout.php"
                       class="text-sm font-medium text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 transition">
                       Logout
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php"
                       class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400'; ?> transition">
                       Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>auth/register.php"
                       class="text-sm px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition">
                       Register
                    </a>
                <?php endif; ?>
            </div>

            <div class="md:hidden flex items-center gap-3">
                <button @click="dark = !dark" x-data
                        class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
                    </svg>
                    <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707.707M6.343 6.343l-.707.707M12 7a5 5 0 100 10A5 5 0 0012 7z"/>
                    </svg>
                </button>

                <button @click="open = !open"
                        class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" x-transition
         class="md:hidden border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 flex flex-col gap-3">
        <a href="<?php echo BASE_URL; ?>pages/home.php"
           class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300'; ?>">
           Home
        </a>
        <a href="<?php echo BASE_URL; ?>pages/report.php"
           class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300'; ?>">
           Report Item
        </a>
        <a href="<?php echo BASE_URL; ?>pages/search.php"
           class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300'; ?>">
           Search
        </a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo BASE_URL; ?>pages/my_items.php"
               class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'my_items.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300'; ?>">
               My Items
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>admin/index.php"
                   class="text-sm font-medium text-gray-600 dark:text-gray-300">
                   Admin Panel
                </a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>auth/logout.php"
               class="text-sm font-medium text-red-500 dark:text-red-400">
               Logout
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>auth/login.php"
               class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-300'; ?>">
               Login
            </a>
            <a href="<?php echo BASE_URL; ?>auth/register.php"
               class="text-sm font-medium text-blue-600 dark:text-blue-400">
               Register
            </a>
        <?php endif; ?>
    </div>
</nav>