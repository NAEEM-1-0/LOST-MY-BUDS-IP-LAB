<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: header.php (Commercial E-Commerce Navbar & Header Engine)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Commercial PHP E-Commerce Store
 * ==============================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/cart_functions.php';

$cart_count = get_cart_count();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " | BudsRecover — আসল বাড রিপ্লেসমেন্ট শপ" : "BudsRecover — The Single Earbud Replacement Hub (PHP E-Commerce)"; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- Commercial Styling -->
    <style>
        :root {
            --bg-body: #0b0f19;
            --bg-card: #111827;
            --bg-card-hover: #17223b;
            --bg-elevated: #1f2937;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(56, 189, 248, 0.4);
            --primary: #0284c7;
            --primary-light: #38bdf8;
            --emerald: #10b981;
            --amber: #f59e0b;
            --rose: #f43f5e;
            --purple: #8b5cf6;
            --text-title: #ffffff;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --text-dark: #0f172a;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.45);
            --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.6);
        }

        body.light-mode {
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-hover: #f1f5f9;
            --bg-elevated: #e2e8f0;
            --border-subtle: #e2e8f0;
            --border-highlight: #38bdf8;
            --text-title: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Hind Siliguri', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* 1. Top E-Commerce Announcement Bar */
        .com-topbar {
            background: linear-gradient(90deg, #1e1b4b, #312e81, #0f172a);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 6px 20px;
            font-size: 0.8rem;
            color: #c7d2fe;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .com-topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .com-promo-badge {
            background: #f43f5e;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .com-topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .com-topbar-link {
            color: #93c5fd;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
        }
        .com-topbar-link:hover {
            color: #ffffff;
        }

        /* 2. Main E-Commerce Header */
        .com-header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-subtle);
            padding: 14px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .com-header-inner {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        /* Brand Logo */
        .com-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .com-logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #0284c7, #6366f1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
        }

        .com-logo-text h1 {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
            color: var(--text-title);
        }
        .com-logo-text h1 span {
            color: #38bdf8;
        }
        .com-logo-tagline {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        /* Commercial Global Search Bar */
        .com-search-form {
            flex: 1;
            max-width: 600px;
            display: flex;
            background: var(--bg-body);
            border: 2px solid var(--border-subtle);
            border-radius: 10px;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .com-search-form:focus-within {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2);
        }

        .com-search-select {
            background: var(--bg-elevated);
            color: var(--text-body);
            border: none;
            padding: 0 12px;
            font-size: 0.82rem;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            border-right: 1px solid var(--border-subtle);
        }

        .com-search-input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 10px 14px;
            font-size: 0.9rem;
            color: var(--text-title);
            outline: none;
        }
        .com-search-input::placeholder {
            color: var(--text-muted);
        }

        .com-search-btn {
            background: linear-gradient(135deg, #0284c7, #2563eb);
            color: white;
            border: none;
            padding: 0 20px;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }
        .com-search-btn:hover {
            opacity: 0.92;
        }

        /* Header Right Action Tools */
        .com-header-tools {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .com-tool-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-title);
            transition: all 0.2s;
            cursor: pointer;
        }
        .com-tool-btn:hover {
            border-color: var(--primary-light);
            transform: translateY(-1px);
        }

        .com-cart-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff !important;
            border: none;
            position: relative;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .com-cart-btn:hover {
            opacity: 0.95;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }

        .com-cart-badge {
            background: #f43f5e;
            color: white;
            font-size: 0.72rem;
            font-weight: 800;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }

        /* 3. Mega Navigation Bar */
        .com-navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0 24px;
        }

        .com-navbar-inner {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .com-navbar-inner::-webkit-scrollbar {
            display: none;
        }

        .com-nav-list {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
            padding: 6px 0;
            white-space: nowrap;
        }

        .com-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-body);
            transition: all 0.2s;
        }
        .com-nav-link:hover {
            color: var(--primary-light);
            background: var(--bg-elevated);
        }
        .com-nav-link.active {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.12);
            font-weight: 700;
        }

        .com-php-tag {
            background: #6366f1;
            color: #fff;
            font-size: 0.68rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Page Container */
        .com-container {
            max-width: 1300px;
            margin: 28px auto;
            padding: 0 20px;
            flex: 1;
            width: 100%;
        }

        /* Commercial Flash Message Alert */
        .com-alert {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }
        .com-alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .com-alert-error {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fb7185;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 900px) {
            .com-header-inner {
                flex-direction: column;
                align-items: stretch;
            }
            .com-search-form {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- 1. Top E-Commerce Announcement Bar -->
    <div class="com-topbar">
        <div class="com-topbar-left">
            <span class="com-promo-badge">🔥 স্পেশাল অফার</span>
            <span>প্রথম অর্ডারে ১০% ছাড়! প্রোমো কোড: <strong style="color: #fde047; font-family: monospace;">BUDS10</strong></span>
            <span>•</span>
            <span>🚚 ৳৫,০০০+ অর্ডারে সারা দেশে ফ্রি ক্যাশ অন ডেলিভারি</span>
        </div>
        <div class="com-topbar-right">
            <span style="color: #93c5fd;">📞 হেল্পলাইন: <strong>+৮৮০১৭১২-LOSTBUD</strong></span>
            <span>•</span>
            <a href="verify_serial.php" class="com-topbar-link">🛡️ আসল OEM যাচাই</a>
            <span>•</span>
            <a href="track.php" class="com-topbar-link">📦 পার্সেল ট্র্যাকিং</a>
        </div>
    </div>

    <!-- 2. Main E-Commerce Header -->
    <header class="com-header">
        <div class="com-header-inner">
            
            <!-- Brand Logo -->
            <a href="index.php" class="com-logo">
                <div class="com-logo-icon">🎧</div>
                <div class="com-logo-text">
                    <h1>Buds<span>Recover</span></h1>
                    <div class="com-logo-tagline">Original Single Earbud Hub</div>
                </div>
            </a>

            <!-- Commercial Global Search Bar -->
            <form action="index.php" method="GET" class="com-search-form">
                <select name="category" class="com-search-select">
                    <option value="">সব পার্টস</option>
                    <option value="Left Bud">🎧 Left Bud</option>
                    <option value="Right Bud">🎧 Right Bud</option>
                    <option value="Charging Case">🔋 Charging Case</option>
                </select>
                <input type="search" name="q" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" 
                       placeholder="মডেল বা পার্ট খুঁজুন (যেমন: AirPods Pro, Galaxy Buds 2, Sony...)" 
                       class="com-search-input">
                <button type="submit" class="com-search-btn">
                    <span>🔍</span>
                    <span>সার্চ</span>
                </button>
            </form>

            <!-- Header Quick Action Tools -->
            <div class="com-header-tools">
                <!-- Theme Toggle Button -->
                <button type="button" class="com-tool-btn" onclick="document.body.classList.toggle('light-mode')" title="ডার্ক ও লাইট মোড পরিবর্তন">
                    <span>🌓</span>
                </button>

                <!-- Admin Button -->
                <a href="admin_portal.php" class="com-tool-btn" title="পিএইচপি অ্যাডমিন প্যানেল">
                    <span>⚙️</span>
                    <span>অ্যাডমিন</span>
                </a>

                <!-- Cart Button with Dynamic Badge -->
                <a href="cart.php" class="com-tool-btn com-cart-btn" title="শপিং ব্যাগ দেখুন">
                    <span>🛒</span>
                    <span>ব্যাগ</span>
                    <span class="com-cart-badge"><?php echo $cart_count; ?></span>
                </a>
            </div>

        </div>
    </header>

    <!-- 3. Category Navigation Bar (Mega Menu) -->
    <nav class="com-navbar">
        <div class="com-navbar-inner">
            <ul class="com-nav-list">
                <li><a href="index.php" class="com-nav-link <?php echo ($current_page == 'index.php' && empty($_GET['category']) && empty($_GET['brand'])) ? 'active' : ''; ?>">🏠 হোম স্টোর</a></li>
                <li><a href="index.php?category=Left Bud" class="com-nav-link <?php echo (isset($_GET['category']) && $_GET['category'] == 'Left Bud') ? 'active' : ''; ?>">🎧 Left Earbuds</a></li>
                <li><a href="index.php?category=Right Bud" class="com-nav-link <?php echo (isset($_GET['category']) && $_GET['category'] == 'Right Bud') ? 'active' : ''; ?>">🎧 Right Earbuds</a></li>
                <li><a href="index.php?category=Charging Case" class="com-nav-link <?php echo (isset($_GET['category']) && $_GET['category'] == 'Charging Case') ? 'active' : ''; ?>">🔋 Charging Cases</a></li>
                <li><a href="index.php?brand=Apple" class="com-nav-link <?php echo (isset($_GET['brand']) && $_GET['brand'] == 'Apple') ? 'active' : ''; ?>">🍏 Apple AirPods</a></li>
                <li><a href="index.php?brand=Samsung" class="com-nav-link <?php echo (isset($_GET['brand']) && $_GET['brand'] == 'Samsung') ? 'active' : ''; ?>">🌌 Samsung Buds</a></li>
                <li><a href="index.php?brand=Sony" class="com-nav-link <?php echo (isset($_GET['brand']) && $_GET['brand'] == 'Sony') ? 'active' : ''; ?>">🎵 Sony WF</a></li>
                <li><a href="index.php?brand=Nothing" class="com-nav-link <?php echo (isset($_GET['brand']) && $_GET['brand'] == 'Nothing') ? 'active' : ''; ?>">⚪ Nothing Ear</a></li>
                <li><a href="verify_serial.php" class="com-nav-link <?php echo ($current_page == 'verify_serial.php') ? 'active' : ''; ?>">🛡️ OEM সত্যতা যাচাই</a></li>
                <li><a href="warranty_claim.php" class="com-nav-link <?php echo ($current_page == 'warranty_claim.php') ? 'active' : ''; ?>">📋 ওয়ারেন্টি ক্লেইম</a></li>
                <li><a href="invoice.php" class="com-nav-link <?php echo ($current_page == 'invoice.php') ? 'active' : ''; ?>">🧾 ইনভয়েস</a></li>
                <li><a href="test_connection.php" class="com-nav-link" style="color: #38bdf8;"><span class="com-php-tag">PHP 8.5</span> ডায়াগনস্টিকস</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="com-container">
        <!-- সেশনের নোটিফিকেশন ফ্ল্যাশ মেসেজ -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="com-alert com-alert-success">
                <span style="font-size: 1.3rem;">✅</span>
                <div><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="com-alert com-alert-error">
                <span style="font-size: 1.3rem;">⚠️</span>
                <div><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
            </div>
        <?php endif; ?>
