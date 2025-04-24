<?php
session_start();
$role = $_SESSION['role'] ?? '';

if ($role === 'client') {
    header("Location: /Projet/catalogue.php");
    exit();
}

$permissions = [
    'admin'    => ['dashboard','stock','users','catalogue','sales','pdf'],
    'supplier' => ['dashboard','stock','catalogue'],
    'seller'   => ['dashboard','catalogue','sales'],
    'client'   => ['catalogue']
];
$allowed = $permissions[$role] ?? [];
require_once('config/db.php');

$classes = [
    ['gradient-text', 'gold'],
    ['gradient-text', 'orange-glow'],
    ['gradient-text', 'bronze'],
    ['gradient-text', 'champ'],
    ['gradient-text', 'champ']
];

// 🔄 Réponse AJAX pour recherche dynamique
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $search = $_GET['q'] ?? '';
    if (!$search) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT name, price 
        FROM products 
        WHERE name LIKE :search 
        ORDER BY name ASC
        LIMIT 5
    ");
    $stmt->execute(['search' => "%$search%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit();
}

// 🔢 Statistiques dynamiques
// Total des ventes (price * quantity)
$stmtSales = $conn->query("
    SELECT SUM(s.quantity * p.price) AS total_sales
    FROM sales s
    JOIN products p ON s.product_id = p.id
");
$totalSales = $stmtSales->fetchColumn() ?? 0;

// Total du stock
$stmtStock = $conn->query("SELECT SUM(stock) FROM products");
$totalStock = $stmtStock->fetchColumn() ?? 0;

// Nombre de clients
$stmtClients = $conn->query("SELECT COUNT(*) FROM clients");
$newClients = $stmtClients->fetchColumn();

// Nombre de commandes
$stmtOrders = $conn->query("SELECT COUNT(*) FROM sales");
$pendingOrders = $stmtOrders->fetchColumn();

// Produits les plus vendus
$stmtTopSold = $conn->query("
    SELECT p.name, SUM(s.quantity) AS total_sold
    FROM sales s
    JOIN products p ON s.product_id = p.id
    GROUP BY p.name
    ORDER BY total_sold DESC
    LIMIT 5
");

$topSold = $stmtTopSold->fetchAll(PDO::FETCH_ASSOC);

// Produits à stock faible, comportement selon rôle
if ($role === 'supplier') {
    // Pour les suppliers : tous les produits dont le stock ≤ 2
    $stmtLowStock = $conn->query("
        SELECT name, stock
        FROM products
        WHERE stock < 2
        ORDER BY stock ASC
    ");
} else {
    // Pour les autres rôles : top 5 produits les plus bas en stock
    $stmtLowStock = $conn->query("
        SELECT name, stock
        FROM products
        ORDER BY stock ASC
        LIMIT 5
    ");
}
$lowStock = $stmtLowStock->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AAAW Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: url('img/dash1.png') no-repeat center center fixed;
            background-size: cover;
            color: white;
            animation: fadeInUp 0.8s ease-in-out;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 80px;
            height: 100vh;
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(14px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 25px 0;
            box-shadow: 3px 0 15px rgba(0,0,0,0.4);
            z-index: 10;
        }
        .menu-items {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .menu-item {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            transition: 0.3s ease;
        }
        .sidebar .menu-item:hover {
            background-color: rgba(255, 140, 0, 0.8);
            color: white;
            transform: scale(1.1);
        }
        .logout-btn {
            margin-bottom: 20px;
        }
        .dashboard-content {
            margin-left: 100px;
            padding: 40px;
        }
        .dashboard-content h1 {
            font-size: 2.8rem;
            font-weight: 700;
        }
        .dashboard-content p {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .card-box {
            background: rgba(255, 255, 255, 0.08);
            padding: 24px;
            border-radius: 20px;
            backdrop-filter: blur(6px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            color: white;
            text-align: center;
            transition: 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease forwards;
            transform: translateY(20px);
            opacity: 0;
        }
        .card-box:nth-child(1) { animation-delay: 0.2s; }
        .card-box:nth-child(2) { animation-delay: 0.4s; }
        .card-box:nth-child(3) { animation-delay: 0.6s; }
        .card-box:nth-child(4) { animation-delay: 0.8s; }
        .card-box::before {
            content: "";
            position: absolute;
            top: -20%;
            left: -20%;
            width: 140%;
            height: 140%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.1), transparent 70%);
            transform: rotate(45deg);
        }
        @keyframes shine {
            0% { transform: rotate(45deg) translate(-100%, -100%); }
            100% { transform: rotate(45deg) translate(100%, 100%); }
        }
        .card-box h4 {
            font-size: 1.5rem;
            margin-bottom: 12px;
            color: #ffa726;
            font-weight: bold;
            animation: fadeInUp 0.8s ease-in-out;
        }
        .card-box p, .card-box ul {
            font-size: 1.1rem;
            color: #f5f5f5;
            list-style: none;
            padding-left: 0;
            font-weight: bold;
        }
        .card-box ul li {
            margin-bottom: 5px;
        }
        .card-section {
            text-align: center;
            margin-top: 60px;
            animation: fadeInContainer 1s ease-in-out;
        }
        .card-section-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffa726;
            text-shadow: 0 0 8px rgba(255, 140, 0, 0.8);
            animation: pulseText 2s infinite;
        }
        .flex-columns {
            justify-content: center;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            padding: 30px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255,140,0,0.4);
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 900px;
            margin: 0 auto;
            animation: fadeInContainer 1.2s ease-in-out;
        }
        .card-mini {
            flex: 1;
            min-width: 300px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 140, 0, 0.3);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(4px);
        }
        .search-bar {
            position: absolute;
            top: 20px;
            right: 40px;
        }
        .search-bar input {
            padding: 10px 20px;
            border-radius: 20px;
            border: none;
            outline: none;
            background-color: rgba(255,255,255,0.2);
            color: white;
            width: 220px;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .suggestions {
            display: none;
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
            background-color: rgba(255,255,255,0.1);
            border-radius: 8px;
            margin-top: 10px;
            padding: 10px;
            color: white;
            max-height: 220px;
            overflow-y: auto;
            position: absolute;
            right: 0px;
            width: 220px;
            z-index: 20;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.2);
            top: 45px;
        }

        .suggestion-item {
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background-color: rgba(255, 140, 0, 0.3);
        }
        .sidebar .dropdown {
            position: relative;
            display: inline-block;
            z-index: 20;
        }

        .sidebar .dropdown::after {
            content: '';
            position: absolute;
            top: 0;
            left: 100%;
            width: 120px;
            height: 100%;
            pointer-events: auto;
        }

        .sidebar .submenu {
            position: absolute;
            top: 50%;
            left: 100%;
            transform: translateX(0) translateY(-50%);
            display: flex;
            flex-direction: row;
            gap: 8px;
            background: rgba(20,20,20,0.8);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.25s ease, opacity 0.25s ease;
            z-index: 30;
        }

        .sidebar .dropdown:hover .submenu,
        .sidebar .submenu:hover {
            opacity: 1;
            pointer-events: auto;
            transform: translateX(10px) translateY(-50%);
        }

        .sidebar .submenu .submenu-item {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-radius: 12px;
            transition: background 0.3s, transform 0.2s;
        }
        .sidebar .submenu .submenu-item:hover {
            background-color: #ff7f00;
            transform: scale(1.1);
        }
        .card-mini {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 140, 0, 0.4);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(8px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .card-mini ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .card-mini ul li {
            animation: fadeInUp 0.8s ease forwards;
            opacity: 0;
            transform: translateY(10px);
            margin-bottom: 12px;
            line-height: 1.6;
            animation-fill-mode: forwards;
        }
        .flex-columns {
            background: rgba(0, 0, 0, 0.4);
            padding: 20px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .gradient-text {
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-text.gold {
            background: linear-gradient(to right, #ff9100, #ffc107, #ffe082);
            text-shadow: 0 0 2px #ffc107;
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-text.orange-glow {
            background: linear-gradient(to right, #ff7043, #ffa726);
            text-shadow: 0 0 3px #ffa726;
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-text.bronze {
            background: linear-gradient(to right, #b87333, #e6b98c);
            text-shadow: 0 0 3px #e6b98c;
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-text.champ {
            background: linear-gradient(to right, #f5f5dc, #fff8e1);
            text-shadow: 0 0 3px #fff8e1;
            opacity: 0.85;
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .gradient-text:hover {
            text-shadow: 0 0 6px rgba(255, 255, 255, 0.6);
            transform: scale(1.02);
            transition: 0.3s ease;
        }
        .gradient-stock {
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 2px rgba(255,255,255,0.2);
        }

        .gradient-stock.zero {
            background: linear-gradient(to right, #ff5252, #ff867c);
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 3px #ff5252;
        }

        .gradient-stock.low {
            background: linear-gradient(to right, #ff9100, #ffc107);
            font-weight: 700;
            display: inline-block;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 3px #ff9100;
        }
        .gradient-stock:hover {
            text-shadow: 0 0 6px rgba(255, 255, 255, 0.6);
            transform: scale(1.03);
            transition: 0.3s ease;
        }
        .glow-pulse {
            animation: glowPulse 1.5s infinite ease-in-out;
        }

        @keyframes glowPulse {
            0% {
                text-shadow: 0 0 6px #ff5252;
            }
            50% {
                text-shadow: 0 0 14px #ff867c;
            }
            100% {
                text-shadow: 0 0 6px #ff5252;
            }
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="menu-items">
        <?php if (in_array('dashboard', $allowed)): ?>
            <a href="/Projet/dashboard_princip.php" class="menu-item" title="Dashboard"><i class="bi bi-speedometer2"></i></a>
        <?php endif; ?>

        <?php if (in_array('stock', $allowed)): ?>
            <?php if ($role === 'supplier'): ?>
                <a href="/Projet/Products/add_product.php" class="menu-item" title="Add Product">
                    <i class="bi bi-box-seam"></i>
                </a>
            <?php else: ?>
                <a href="/Projet/Products/dashboard_products.php" class="menu-item" title="Stock">
                    <i class="bi bi-box-seam"></i>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('users', $allowed)): ?>
            <div class="dropdown">
                <a class="menu-item" title="Users & Clients">
                    <i class="bi bi-people-fill"></i>
                </a>
                <div class="submenu">
                    <a href="/Projet/Users/dashboard_users.php" class="submenu-item" title="Manage Users">
                        <i class="bi bi-person-lines-fill"></i>
                    </a>
                    <a href="/Projet/Clients/dashboard_clients.php" class="submenu-item" title="Manage Clients">
                        <i class="bi bi-people"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array('catalogue', $allowed)): ?>
            <a href="/Projet/catalogue.php" class="menu-item" title="Catalogue"><i class="bi bi-car-front-fill"></i></a>
        <?php endif; ?>

        <?php if (in_array('sales', $allowed)): ?>
            <div class="dropdown">
                <a class="menu-item" title="Sales">
                    <i class="bi bi-currency-dollar"></i>
                </a>

                <div class="submenu">
                    <a href="/Projet/Sales/add_sale.php" class="submenu-item" title="Register Sale">
                        <i class="bi bi-plus-circle"></i>
                    </a>

                    <a href="/Projet/Sales/sales_overview.php" class="submenu-item" title="Sales Overview">
                        <i class="bi bi-bar-chart-line"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array('pdf', $allowed)): ?>
            <a href="/Projet/PDF/generate_reports.php" class="menu-item" title="Convert to PDF"><i class="bi bi-file-earmark-pdf-fill"></i></a>
        <?php endif; ?>
    </div>
    <div class="logout-btn">
        <a href="/Projet/logout.php" class="menu-item" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<div class="dashboard-content">
    <div class="search-bar">
        <input type="text" placeholder="Search products...">
        <div class="suggestions"></div>
    </div>

    <h1>Welcome Back to AAAW Dashboard</h1>
    <p>Your latest insights and stats are below:</p>

    <?php if (in_array($role, ['admin','seller'])): ?>
        <div class="dashboard-cards">
            <div class="card-box">
                <h4>Total Sales</h4>
                <p><?= number_format($totalSales, 0, '', ' ') ?> €</p>
            </div>
            <div class="card-box">
                <h4>Products in Stock</h4>
                <p><?= $totalStock ?> Vehicles</p>
            </div>
            <div class="card-box">
                <h4>Total Clients</h4>
                <p><?= $newClients ?> Clients</p>
            </div>
            <div class="card-box">
                <h4>Total Orders</h4>
                <p><?= $pendingOrders ?> Orders</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array($role, ['admin','seller'])): ?>
        <div class="card-section">
            <h3 class="card-section-title">Top Most Sold Products</h3>
            <div class="flex-columns">
                <div class="card-mini">
                    <ul>
                        <?php foreach ($topSold as $index => $product):
                            $classList = $classes[$index] ?? ['gradient-text', 'smoke'];
                            $classAttr = implode(' ', $classList);
                            ?>
                            <li>
                            <span class="<?= $classAttr ?>">
                                <?= ($index + 1) . '. ' . htmlspecialchars($product['name']) ?>
                                <span style="margin-left: 6px;">(<?= htmlspecialchars($product['total_sold']) ?> sold)</span>
                            </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array($role, ['admin','supplier'])): ?>
        <div class="card-section">
            <h3 class="card-section-title">Products with Low Stock</h3>
            <div class="flex-columns">
                <div class="card-mini">
                    <ul>
                        <?php foreach ($lowStock as $index => $product):
                            $stock = (int)$product['stock'];
                            $stockClass = 'gradient-stock ' . ($stock === 0 ? 'zero glow-pulse' : 'low');
                            ?>
                            <li>
        <span class="<?= $stockClass ?>">
            <?= ($index + 1) . '. ' . htmlspecialchars($product['name']) ?> (<?= $stock ?> left)
        </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.querySelector('.search-bar input');
        const suggestionBox = document.querySelector('.suggestions');

        input.addEventListener('input', function () {
            const query = input.value.trim();
            if (query.length < 2) {
                suggestionBox.innerHTML = '';
                suggestionBox.style.display = 'none';
                suggestionBox.style.opacity = '0';
                return;
            }

            fetch(`?ajax=1&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    suggestionBox.innerHTML = '';

                    if (data.length === 0) {
                        suggestionBox.innerHTML = '<div class="suggestion-item">No match found</div>';
                        suggestionBox.style.display = 'block';
                        suggestionBox.style.animation = 'fadeIn 0.3s ease forwards';
                        return;
                    }

                    suggestionBox.style.display = 'block';
                    suggestionBox.style.animation = 'fadeIn 0.3s ease forwards';

                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.classList.add('suggestion-item');
                        const formattedPrice = Number(item.price).toLocaleString('fr-FR') + ' €';
                        div.innerHTML = `<strong>${item.name}</strong><br><em>${formattedPrice}</em>`;

                        // ← Ajoute ce listener pour rediriger
                        div.addEventListener('click', () => {
                            // Ajuste le chemin si besoin (ici on part de /Projet/dashboard_princip.php)
                            window.location.href = '/Projet/catalogue.php?q=' + encodeURIComponent(item.name);
                        });

                        suggestionBox.appendChild(div);
                    });
                })
                .catch(() => {
                    suggestionBox.style.display = 'none';
                    suggestionBox.style.opacity = '0';
                });
        });
    });
        document.addEventListener("DOMContentLoaded", () => {
        const lowStockItems = document.querySelectorAll(".card-section ul li");

        lowStockItems.forEach((item, index) => {
        const delay = 0.1 * index; // 0.1s d'écart par item
        item.style.animationDelay = `${delay}s`;
    });
    });
</script>
</body>
</html>
