<?php
session_start();
$role = $_SESSION['role'] ?? '';

$permissions = [
    'admin'    => ['dashboard','stock','users','catalogue','sales','pdf'],
    'supplier' => ['dashboard','stock','catalogue'],
    'seller'   => ['dashboard','catalogue','sales'],
    'client'   => ['catalogue']
];
$allowed = $permissions[$role] ?? [];
require_once('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$search = $_GET['q'] ?? '';
$products = [];

if ($search) {
    $stmt = $conn->prepare("
        SELECT * FROM products 
        WHERE name LIKE :search OR description LIKE :search 
        ORDER BY name ASC
    ");
    $stmt->execute(['search' => "%$search%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT * FROM products ORDER BY name ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Catalogue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@500;700;900&display=swap');
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: url('img/catalogue.png') no-repeat center center fixed;
            background-size: cover;
            color: white;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(10px);
            z-index: -1;
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
        .menu-item:hover {
            background-color: #ff7f00;
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(255, 127, 0, 0.5);
        }
        .logout-btn {
            margin-bottom: 10px;
        }
        .main-content {
            margin-left: 100px;
            padding: 40px;
        }
        .catalogue-title {
            text-align: center;
            padding: 30px 0;
            margin-bottom: 0px;
            color: #ffa726;
            font-weight: 800;
            font-size: 2.2rem;
        }
        .product-card {
            height: 100%;
            background-color: #1f1f1f;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease;
            animation: fadeInUp 0.6s ease-in-out;
        }
        .product-card:hover {
            transform: scale(1.02);
        }
        .product-img {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .card-body {
            color: white;
        }
        .card-footer {
            background: #333;
            font-weight: 600;
        }
        .available-stock {
            color: #4caf50;
        }
        .out-of-stock {
            color: #f44336;
        }
        .out-of-stock del {
            color: #f44336;
        }
        .search-bar {
            text-align: center;
            margin-bottom: 20px;
        }
        .search-bar input {
            width: 350px;
            padding: 10px 15px;
            border-radius: 25px;
            border: none;
            outline: none;
            background-color: rgba(255,255,255,0.2);
            color: white;
        }
        .main-content {
            animation: fadeInUp 1s ease-in-out;
        }

        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
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

<div class="main-content">
    <h1 class="catalogue-title">Catalogue</h1>

    <div class="search-bar">
        <input type="text" id="search-input" placeholder="Search a car by name or description..." value="<?= htmlspecialchars($search) ?>">
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-4" id="product-list">
        <?php foreach ($products as $product): ?>
            <div class="col">
                <div class="card product-card shadow-sm">
                    <img src="<?php echo !empty($product['image_url']) ? $product['image_url'] : 'img/default_car.jpg'; ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                        <p><strong>Price:</strong> <?php echo number_format($product['price'], 0, '', ' '); ?> €</p>
                        <p><strong>Category:</strong> <?php echo $product['category']; ?> | <strong>Brand:</strong> <?php echo $product['supplier']; ?></p>
                    </div>
                    <div class="card-footer text-muted">
                        <?php if ($product['stock'] == 0): ?>
                            <small class="out-of-stock"><del>Available in store</del> (Out of stock)</small>
                        <?php else: ?>
                            <small class="available-stock">Available in store</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const input = document.getElementById("search-input");
        input.addEventListener("input", function () {
            const query = input.value.trim();
            const url = `catalogue.php?q=${encodeURIComponent(query)}`;
            window.history.replaceState({}, '', url);
            fetch(url)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newGrid = doc.querySelector("#product-list");
                    document.querySelector("#product-list").replaceWith(newGrid);
                });
        });
    });
</script>

</body>
</html>
