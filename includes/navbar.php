<nav>
    <div class="nav-brand">
        <a href="<?php echo BASE_URL; ?>pages/home.php">Lost & Found</a>
    </div>
    <div class="nav-links">
        <a href="<?php echo BASE_URL; ?>pages/home.php" 
           <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'class="active"' : ''; ?>>
           Home
        </a>
        <a href="<?php echo BASE_URL; ?>pages/report.php"
           <?php echo (basename($_SERVER['PHP_SELF']) == 'report.php') ? 'class="active"' : ''; ?>>
           Report Item
        </a>
        <a href="<?php echo BASE_URL; ?>pages/search.php"
           <?php echo (basename($_SERVER['PHP_SELF']) == 'search.php') ? 'class="active"' : ''; ?>>
           Search
        </a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo BASE_URL; ?>pages/my_items.php"
               <?php echo (basename($_SERVER['PHP_SELF']) == 'my_items.php') ? 'class="active"' : ''; ?>>
               My Items
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>admin/index.php"
                   <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'class="active"' : ''; ?>>
                   Admin Panel
                </a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>auth/login.php"
               <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'class="active"' : ''; ?>>
               Login
            </a>
            <a href="<?php echo BASE_URL; ?>auth/register.php"
               <?php echo (basename($_SERVER['PHP_SELF']) == 'register.php') ? 'class="active"' : ''; ?>>
               Register
            </a>
        <?php endif; ?>
    </div>
</nav>