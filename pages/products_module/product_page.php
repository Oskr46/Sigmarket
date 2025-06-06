<?php 
include('../../components/header_footer.php');
include('../../components/conexion.php');
session_start();
$conn = connectDB();
mysqli_set_charset($conn, "utf8");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Obtener parámetros de filtro y búsqueda
$categoria = $_GET['category'] ?? null;
$color = $_GET['color'] ?? null;
$searchTerm = $_GET['search'] ?? null;

// Construir consulta SQL con filtros - MODIFICADO PARA INCLUIR NOMBRE DE CATEGORÍA
$q = "SELECT p.*, i.urlImage, c.nameCategory 
      FROM products p
      LEFT JOIN imageproduct i ON p.idProduct = i.idProduct
      LEFT JOIN category c ON p.categoryProduct = c.idCategory
      WHERE 1=1";
$params = [];

if ($searchTerm) {
    $q .= " AND (p.nameProduct LIKE ? OR p.descriptionProduct LIKE ?)";
    $searchParam = "%" . $searchTerm . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($categoria && $categoria != 'todas') {
    $q .= " AND c.nameCategory = ?";
    $params[] = $categoria;
}

if ($color && $color != 'todos') {
    $q .= " AND p.colorProduct = ?";
    $params[] = $color;
}

// Agrupar por ID de producto para evitar duplicados
$q .= " GROUP BY p.idProduct";

// Preparar y ejecutar consulta
$stmt = mysqli_prepare($conn, $q);

if ($params) {
    $types = str_repeat('s', count($params)); // Tipos de parámetros (todos son strings)
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$consulta = mysqli_stmt_get_result($stmt);

// Obtener categorías únicas para el filtro (con nombres)
$categorias_query = mysqli_query($conn, 
    "SELECT DISTINCT c.idCategory, c.nameCategory 
     FROM products p
     JOIN category c ON p.categoryProduct = c.idCategory
     ORDER BY c.nameCategory");
$categorias = [];
while ($row = mysqli_fetch_assoc($categorias_query)) {
    $categorias[$row['idCategory']] = $row['nameCategory'];
}

// Obtener colores únicos para el filtro
$colores_query = mysqli_query($conn, "SELECT DISTINCT colorProduct FROM products WHERE colorProduct IS NOT NULL AND colorProduct != ''");
$colores = [];
while ($row = mysqli_fetch_assoc($colores_query)) {
    $colores[] = $row['colorProduct'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - SIGMARKET</title>
    <link rel="icon" href="<?php echo BASE_URL?>res/img/favicon_white.png"/>
    <link rel="stylesheet" href="<?php echo BASE_URL?>styles/catalogue.css"/>
    <link rel="stylesheet" href="<?php echo BASE_URL?>styles/product_page.css"/>
    <link rel="stylesheet" href="<?php echo BASE_URL?>styles/global.css"/>
</head>
<body>
    <header class="header">
        <?php show_header(); ?>
    </header>
    
    <main class="product-page">
        <aside class="filters">
            <form method="GET" action="">
                <?php if($searchTerm): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                <?php endif; ?>
                
                <section class="filter-section">
                    <h2>Filtros</h2>
                    
                    <!-- Mostrar término de búsqueda si existe -->
                    <?php if($searchTerm): ?>
                        <div style="margin-bottom: 15px;">
                            <h3>Búsqueda: "<?php echo htmlspecialchars($searchTerm); ?>"</h3>
                            <a href="?" style="font-size: 0.8rem; color: #6c757d;">Limpiar búsqueda</a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filtro de categorías -->
                    <h3>Categorías</h3>
                    <div class="filter-option">
                        <input type="radio" name="category" id="cat_todas" value="todas" 
                               <?= (!$categoria || $categoria == 'todas') ? 'checked' : '' ?>>
                        <label for="cat_todas">Todas las categorías</label>
                    </div>
                    
                    <?php foreach ($categorias as $id => $nombre): ?>
                        <div class="filter-option">
                            <input type="radio" name="category" id="cat_<?= htmlspecialchars($id) ?>" 
                                   value="<?= htmlspecialchars($nombre) ?>" 
                                   <?= ($categoria === $nombre) ? 'checked' : '' ?>>
                            <label for="cat_<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($nombre) ?></label>
                        </div>
                    <?php endforeach; ?>
                </section>

                <!-- Filtro de colores -->
                <section class="filter-section">
                    <h3>Colores</h3>
                    <div class="filter-option">
                        <input type="radio" name="color" id="color_todos" value="todos" 
                               <?= (!$color || $color == 'todos') ? 'checked' : '' ?>>
                        <label for="color_todos">Todos los colores</label>
                    </div>
                    
                    <?php foreach ($colores as $col): ?>
                        <div class="filter-option" style="display: inline-block; margin-right: 10px;">
                            <input type="radio" name="color" id="color_<?= htmlspecialchars($col) ?>" 
                                   value="<?= htmlspecialchars($col) ?>" 
                                   <?= ($color === $col) ? 'checked' : '' ?>>
                            <label for="color_<?= htmlspecialchars($col) ?>" style="cursor: pointer;">
                                <span style="display: inline-block; width: 20px; height: 20px; background: <?= htmlspecialchars($col) ?>; border-radius: 50%; border: 1px solid #ddd;"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </section>

                <button type="submit" class="apply-filters">Aplicar Filtros</button>
                <a href="?<?= $searchTerm ? 'search='.urlencode($searchTerm) : '' ?>" class="apply-filters" style="background: #6c757d; font-size: 0.8rem; display: inline-block; text-decoration: none; margin-left: 2px;">Limpiar</a>
            </form>
        </aside>

        <section class="product-grid">
            <?php if (mysqli_num_rows($consulta) > 0): ?>
                <?php while($fila = mysqli_fetch_assoc($consulta)): ?>
                    <a href="<?php echo BASE_URL; ?>pages/products_module/detalle_prod?id=<?= $fila['idProduct'] ?>" class="product-card-link">
                        <div class="product-card">
                            <?php if (!empty($fila['urlImage'])): ?>
                                <img src="<?php echo BASE_URL . htmlspecialchars($fila['urlImage']) ?>" 
                                     alt="<?= htmlspecialchars($fila['nameProduct']) ?>" 
                                     class="product-image">
                            <?php else: ?>
                                <div class="product-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <span>Sin imagen</span>
                                </div>
                            <?php endif; ?>
                            
                            <h3 class="product-title"><?= htmlspecialchars($fila['nameProduct']) ?></h3>
                            <p class="product-price">$<?= number_format($fila['priceProduct'], 2) ?></p>
                            <p class="product-stock">Disponibles: <?= htmlspecialchars($fila['quantityProduct']) ?></p>
                            <p class="product-category"><?= htmlspecialchars($fila['nameCategory'] ?? 'Sin categoría') ?></p>
                            
                            <?php if (!empty($fila['descriptionProduct'])): ?>
                                <p style="font-size: 0.9rem; color: #666; margin-top: 10px;">
                                    <?= htmlspecialchars(substr($fila['descriptionProduct'], 0, 100)) ?><?= strlen($fila['descriptionProduct']) > 100 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($fila['colorProduct'])): ?>
                                <div style="margin-top: 10px;">
                                    <span style="display: inline-block; width: 15px; height: 15px; background: <?= htmlspecialchars($fila['colorProduct']) ?>; border-radius: 50%; border: 1px solid #ddd;"></span>
                                    <span style="margin-left: 5px; font-size: 0.8rem;"><?= htmlspecialchars($fila['colorProduct']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="grid-column: 1 / -1; text-align: center; padding: 20px;">
                    No se encontraron productos <?= $searchTerm ? 'para "'.htmlspecialchars($searchTerm).'"' : 'con los filtros seleccionados' ?>.
                </p>
            <?php endif; ?>
        </section>
    </main>

    <footer class="footer">
        <?php show_footer(); ?>
    </footer>
</body>
</html>