<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

// تنظیمات زبان
if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'fa';
$translations = [
    'en' => [
        'welcome' => 'SQLite System Maker', 'upload_db' => 'Upload SQLite Database', 'db_name' => 'Database Name',
        'change_name' => 'Change Name', 'table_name' => 'Table Name', 'field_name' => 'Field Name',
        'data_type' => 'Data Type', 'view_without_password' => 'View Without Password', 'show_search' => 'Show Search',
        'show_table' => 'Show Table', 'show_field' => 'Show Field', 'show_total' => 'Show Total', 'export_to_excel' => 'Export to Excel',
        'username' => 'Username', 'password' => 'Password', 'create_system' => 'Create System', 'login' => 'Login',
        'logout' => 'Logout', 'add_record' => 'Add Record', 'edit' => 'Edit', 'delete' => 'Delete', 'view' => 'View',
        'search' => 'Search', 'undo' => 'Undo', 'records_per_page' => 'Records per page', 'total' => 'Total',
        'generated_system' => 'System generated successfully!', 'system_link' => 'Access your system here', 'login_error' => 'Invalid username or password',
        'login_required' => 'Please login to view this table', 'login_success' => 'Login successful!', 'enable_login' => 'Enable Login',
        'confirm_delete' => 'Are you sure you want to delete this record?' // اضافه شده برای تأیید حذف
    ],
    'fa' => [
        'welcome' => 'سامانه ساز SQLite', 'upload_db' => 'آپلود پایگاه داده', 'db_name' => 'نام پایگاه داده',
        'change_name' => 'تغییر نام', 'table_name' => 'نام جدول', 'field_name' => 'نام فیلد', 'data_type' => 'نوع داده',
        'view_without_password' => 'نمایش بدون رمز عبور', 'show_search' => 'نمایش جستجو', 'show_table' => 'نمایش جدول',
        'show_field' => 'نمایش فیلد', 'show_total' => 'نمایش مجموع', 'export_to_excel' => 'خروجی به اکسل',
        'username' => 'نام کاربری', 'password' => 'رمز عبور', 'create_system' => 'ایجاد سیستم', 'login' => 'ورود',
        'logout' => 'خروج', 'add_record' => 'افزودن رکورد', 'edit' => 'ویرایش', 'delete' => 'حذف', 'view' => 'نمایش',
        'search' => 'جستجو', 'undo' => 'بازگشت', 'records_per_page' => 'رکوردها در هر صفحه', 'total' => 'مجموع',
        'generated_system' => 'سیستم با موفقیت ایجاد شد!', 'system_link' => 'دسترسی به سیستم', 'login_error' => 'نام کاربری یا رمز عبور اشتباه است',
        'login_required' => 'لطفاً برای مشاهده این جدول وارد شوید', 'login_success' => 'ورود با موفقیت انجام شد!', 'enable_login' => 'فعال‌سازی ورود',
        'confirm_delete' => 'آیا مطمئن هستید که می‌خواهید این رکورد را حذف کنید؟' // اضافه شده برای تأیید حذف
    ]
];

function t($key) {
    global $translations;
    return $translations[$_SESSION['lang']][$key] ?? $key;
}

// اعتبارسنجی SQLite
function is_sqlite($file) {
    $header = file_get_contents($file, false, null, 0, 16);
    return strpos($header, 'SQLite format 3') === 0;
}

// گرفتن ساختار دیتابیس
function get_db_structure($db_path) {
    $db = new PDO("sqlite:$db_path");
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $structure = [];
    foreach ($tables as $table) {
        $columns = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $structure[$table] = array_map(fn($col) => [
            'name' => $col['name'], 
            'type' => $col['type'], 
            'pk' => $col['pk']
        ], $columns);
    }
    return $structure;
}

// تولید سیستم
function generate_system($config) {
    global $translations;
    $db_name = $config['db_name'] ?? ($_SESSION['db_name'] ?? 'system');
    $output_dir = $db_name . '_system';
    $db_path = "$output_dir/database.db";

    // ایجاد فولدر و انتقال دیتابیس
    if (!is_dir($output_dir)) mkdir($output_dir, 0755, true);
    if (isset($_SESSION['temp_db_path']) && file_exists($_SESSION['temp_db_path'])) {
        rename($_SESSION['temp_db_path'], $db_path);
        unset($_SESSION['temp_db_path']);
    }

    // گرفتن جداول انتخاب‌شده
    $selected_tables = [];
    foreach ($config['tables'] ?? [] as $table => $settings) {
        if (isset($settings['show_table'])) {
            $selected_tables[$table] = $settings;
        }
    }
    $table_list = array_keys($selected_tables);

    // چک کردن تیک فعال‌سازی لاگین
    $enable_login = isset($config['enable_login']);

    // فایل اصلی سیستم (index.php)
    $index_content = '<?php
session_start();
$db = new PDO("sqlite:database.db");
$tables = ' . var_export($table_list, true) . ';
$translations = ' . var_export($translations, true) . ';
function t($key) { global $translations; return $translations[$_SESSION["lang"] ?? "fa"][$key] ?? $key; }
' . ($enable_login ? 'if (isset($_GET["logout"])) { unset($_SESSION["loggedin"]); header("Location: index.php"); exit; }' : '') . '
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION["lang"] ?? "fa"; ?>" dir="<?php echo ($_SESSION["lang"] ?? "fa") === "fa" ? "rtl" : "ltr"; ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo htmlspecialchars("' . $db_name . '"); ?></title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; }
.header { background: #007bff; color: #fff; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 10; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; display: flex; flex-wrap: wrap; }
.sidebar { width: 100%; padding: 15px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 10px; }
.main { width: 100%; padding: 15px; }
.card { background: #fff; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.btn { padding: 8px 15px; text-decoration: none; color: #fff; border-radius: 5px; display: inline-block; transition: background 0.3s; }
.btn-primary { background: #007bff; } .btn-primary:hover { background: #0056b3; }
.btn-danger { background: #dc3545; } .btn-danger:hover { background: #b02a37; }
.btn-success { background: #28a745; } .btn-success:hover { background: #218838; }
.btn-warning { background: #ffc107; color: #000; } .btn-warning:hover { background: #e0a800; }
ul { list-style: none; padding: 0; margin: 0; display: flex; gap: 10px; flex-wrap: wrap; }
@media (max-width: 768px) { .sidebar, .main { width: 100%; } .sidebar { flex-direction: column; gap: 5px; } .card { padding: 10px; } .btn { width: auto; text-align: center; } }
</style></head><body><div class="header"><h1><?php echo htmlspecialchars("' . $db_name . '"); ?></h1></div><div class="container">
<div class="sidebar">';
    if ($enable_login) {
        $index_content .= '<?php if (isset($_SESSION["loggedin"])) { echo "<a href=\"?logout=1\" class=\"btn btn-danger\">' . t('logout') . '</a>"; } else { echo "<a href=\"login.php\" class=\"btn btn-primary\">' . t('login') . '</a>"; } ?>';
    }
    $index_content .= '<ul><?php foreach ($tables as $table) { echo "<li><a href=\"$table.php\" class=\"btn btn-primary\">$table</a></li>"; } ?></ul></div>
<div class="main"><h2>' . t('welcome') . '</h2><?php foreach ($tables as $table) { echo "<div class=\"card\"><h3>$table</h3><a href=\"$table.php\" class=\"btn btn-primary\">' . t('view') . '</a></div>"; } ?></div>
</div></body></html>';
    file_put_contents("$output_dir/index.php", $index_content);

    // فایل لاگین (بدون تغییر)
    if ($enable_login) {
        $username = isset($config['username']) && !empty($config['username']) ? $config['username'] : 'admin';
        $password = isset($config['password']) && !empty($config['password']) ? $config['password'] : 'password';
        $login_content = '<?php
session_start();
$valid_username = "' . htmlspecialchars($username) . '";
$valid_password = "' . htmlspecialchars($password) . '";
$translations = ' . var_export($translations, true) . ';
function t($key) { global $translations; return $translations[$_SESSION["lang"] ?? "fa"][$key] ?? $key; }
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["username"]) && isset($_POST["password"]) && $_POST["username"] === $valid_username && $_POST["password"] === $valid_password) {
        $_SESSION["loggedin"] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = t("login_error");
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION["lang"] ?? "fa"; ?>" dir="<?php echo ($_SESSION["lang"] ?? "fa") === "fa" ? "rtl" : "ltr"; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . t('login') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f0f2f5; }
        .card { max-width: 400px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { padding: 10px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px; width: 100%; text-align: center; transition: background 0.3s; }
        .btn:hover { background: #0056b3; }
        input { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        .error { color: #dc3545; text-align: center; margin-bottom: 10px; }
        @media (max-width: 768px) { .card { margin: 20px auto; padding: 15px; } input, .btn { font-size: 14px; } }
    </style>
</head>
<body>
    <div class="card">
        <h3>' . t('login') . '</h3>
        <?php if ($error) echo "<p class=\"error\">$error</p>"; ?>
        <form method="post">
            <input type="text" name="username" placeholder="' . t('username') . '" value="' . htmlspecialchars($username) . '" required>
            <input type="password" name="password" placeholder="' . t('password') . '" required>
            <button type="submit" class="btn">' . t('login') . '</button>
        </form>
    </div>
</body>
</html>';
        file_put_contents("$output_dir/login.php", $login_content);
    }

    // تولید صفحات جدول
    $tables = get_db_structure($db_path);
    foreach ($selected_tables as $table => $settings) {
        $new_table_name = $settings['name'] ?? $table;
        $view_no_pass = isset($settings['view_without_password']);
        $show_search = isset($settings['show_search']);
        $columns = $tables[$table];
        $table_content = '<?php
session_start();
$db = new PDO("sqlite:database.db");
$tables = ' . var_export($table_list, true) . ';
$translations = ' . var_export($translations, true) . ';
function t($key) { global $translations; return $translations[$_SESSION["lang"] ?? "fa"][$key] ?? $key; }
' . ($enable_login ? '$view_no_pass = ' . var_export($view_no_pass, true) . ';
if (!$view_no_pass && !isset($_SESSION["loggedin"])) { header("Location: login.php"); exit("<p>" . t("login_required") . "</p>"); }' : '') . '
$per_page = $_GET["per_page"] ?? 5; $page = $_GET["page"] ?? 1; $offset = ($page - 1) * $per_page;
$search = $_GET["search"] ?? ""; $sort = $_GET["sort"] ?? "' . $columns[0]['name'] . '"; $order = $_GET["order"] ?? "DESC";
$query = "SELECT * FROM ' . $table . '"; if ($search && ' . var_export($show_search, true) . ') {
    $query .= " WHERE " . implode(" LIKE \'%$search%\' OR ", array_map(fn($c) => $c["name"], ' . var_export($columns, true) . ')) . " LIKE \'%$search%\'";
}
$total = $db->query("SELECT COUNT(*) FROM ' . $table . '")->fetchColumn(); $pages = ceil($total / $per_page);
$query .= " ORDER BY $sort $order LIMIT $offset, $per_page"; $rows = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION["lang"] ?? "fa"; ?>" dir="<?php echo ($_SESSION["lang"] ?? "fa") === "fa" ? "rtl" : "ltr"; ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $new_table_name . ' - <?php echo htmlspecialchars("' . $db_name . '"); ?></title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; }
.header { background: #007bff; color: #fff; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 10; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; display: flex; flex-wrap: wrap; }
.sidebar { width: 100%; padding: 15px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 10px; }
.main { width: 100%; padding: 15px; } .table-card { background: #fff; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 10px; text-align: ' . ($_SESSION['lang'] === 'fa' ? 'right' : 'left') . '; }
th { background: #f8f9fa; } .btn { padding: 8px 15px; text-decoration: none; color: #fff; border-radius: 5px; display: inline-block; transition: background 0.3s; }
.btn-primary { background: #007bff; } .btn-primary:hover { background: #0056b3; } .btn-danger { background: #dc3545; } .btn-danger:hover { background: #b02a37; }
.btn-success { background: #28a745; } .btn-success:hover { background: #218838; } .btn-warning { background: #ffc107; color: #000; } .btn-warning:hover { background: #e0a800; }
input, select { padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 5px; width: auto; } .pagination { margin-top: 15px; text-align: center; }
ul { list-style: none; padding: 0; margin: 0; display: flex; gap: 10px; flex-wrap: wrap; }
@media (max-width: 768px) { .sidebar, .main { width: 100%; } .sidebar { flex-direction: column; gap: 5px; } table { display: block; } th, td { display: block; width: 100%; text-align: center; } 
th { background: #e9ecef; } .btn, input, select { width: auto; margin: 5px 0; } .pagination a { margin: 0 5px; } }
</style></head><body><div class="header"><h1><?php echo htmlspecialchars("' . $db_name . '"); ?></h1></div><div class="container"><div class="sidebar">
' . ($enable_login ? '<?php if (isset($_SESSION["loggedin"])) { ?><a href="index.php?logout=1" class="btn btn-danger">' . t('logout') . '</a><?php } else { ?><a href="login.php" class="btn btn-primary">' . t('login') . '</a><?php } ?>' : '') . '
<ul><?php foreach ($tables as $table) { echo "<li><a href=\"$table.php\" class=\"btn btn-primary\">$table</a></li>"; } ?></ul></div><div class="main"><h2>' . $new_table_name . '</h2><div class="table-card">';
        if ($show_search) $table_content .= '<form method="get"><input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>">
<button type="submit" class="btn btn-primary">' . t('search') . '</button><a href="' . $new_table_name . '.php" class="btn btn-danger">' . t('undo') . '</a></form>';
        $table_content .= '<a href="add_' . $table . '.php" class="btn btn-success">' . t('add_record') . '</a>
<select onchange="window.location.href=\'?per_page=\'+this.value"><?php foreach ([5, 10, 50, "all"] as $opt) echo "<option value=\"$opt\" " . ($per_page == $opt ? "selected" : "") . ">$opt</option>"; ?></select>
<table><tr>';
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_field']) && stripos($col['type'], 'text') === false) {
                $new_col_name = $settings['columns'][$col['name']]['name'] ?? $col['name'];
                $table_content .= "<th><a href=\"?sort=$col[name]&order=" . ($sort == $col['name'] && $order == 'ASC' ? 'DESC' : 'ASC') . "\">$new_col_name " . ($sort == $col['name'] ? ($order == 'ASC' ? '↑' : '↓') : '') . "</a></th>";
            }
        }
        $table_content .= '<th>' . t('actions') . '</th></tr><?php foreach ($rows as $row) { echo "<tr>";';
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_field']) && stripos($col['type'], 'text') === false) {
                $table_content .= 'echo "<td>" . htmlspecialchars($row["' . $col['name'] . '"] ?? "") . "</td>";';
            }
        }
        $table_content .= 'echo "<td><a href=\"edit_' . $table . '.php?id=" . ($row["' . $columns[0]['name'] . '"] ?? "") . "\" class=\"btn btn-warning\">' . t('edit') . '</a> 
<a href=\"delete_' . $table . '.php?id=" . ($row["' . $columns[0]['name'] . '"] ?? "") . "\" class=\"btn btn-danger\" onclick=\"return confirm(\'' . t('confirm_delete') . '\');\">' . t('delete') . '</a> 
<a href=\"view_' . $table . '.php?id=" . ($row["' . $columns[0]['name'] . '"] ?? "") . "\" class=\"btn btn-primary\">' . t('view') . '</a></td>"; echo "</tr>"; }';
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_total']) && stripos($col['type'], 'int') !== false) {
                $table_content .= 'echo "<tr><td colspan=\"" . (count(array_filter(array_column(' . var_export($columns, true) . ', "type"), fn($t) => stripos($t, "text") === false))) . "\">' . t('total') . '</td><td>" . ($db->query("SELECT SUM(' . $col['name'] . ') FROM ' . $table . '")->fetchColumn() ?? 0) . "</td></tr>";';
            }
        }
        $table_content .= '?></table><div class="pagination"><?php for ($i = 1; $i <= $pages; $i++) echo "<a href=\"?page=$i&per_page=$per_page\" class=\"btn btn-primary " . ($page == $i ? "btn-danger" : "") . "\">$i</a> "; ?></div></div></div></div></body></html>';
        file_put_contents("$output_dir/$new_table_name.php", $table_content);

        // صفحه افزودن
        $add_content = '<?php
session_start();
' . ($enable_login ? 'if (!isset($_SESSION["loggedin"])) { header("Location: login.php"); exit; }' : '') . '
$db = new PDO("sqlite:database.db");
$tables = ' . var_export($table_list, true) . ';
$translations = ' . var_export($translations, true) . ';
function t($key) { global $translations; return $translations[$_SESSION["lang"] ?? "fa"][$key] ?? $key; }
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = [];';
        $insert_columns = [];
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_field']) && !$col['pk']) {
                $add_content .= '$data["' . $col['name'] . '"] = $_POST["' . $col['name'] . '"] ?? "";';
                $insert_columns[] = $col['name'];
            }
        }
        $add_content .= '$stmt = $db->prepare("INSERT INTO ' . $table . ' (' . implode(',', $insert_columns) . ') VALUES (' . implode(',', array_map(fn($c) => ':' . $c, $insert_columns)) . ')"); 
$stmt->execute($data); header("Location: ' . $new_table_name . '.php"); exit; }
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION["lang"] ?? "fa"; ?>" dir="<?php echo ($_SESSION["lang"] ?? "fa") === "fa" ? "rtl" : "ltr"; ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . t('add_record') . ' - <?php echo htmlspecialchars("' . $db_name . '"); ?></title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; }
.header { background: #007bff; color: #fff; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 10; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; display: flex; flex-wrap: wrap; }
.sidebar { width: 20%; padding: 15px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
.main { width: 75%; padding: 15px; } .form-card { background: #fff; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.btn { padding: 10px; background: #28a745; color: #fff; text-decoration: none; border-radius: 5px; width: 100%; text-align: center; transition: background 0.3s; }
.btn:hover { background: #218838; } input, textarea { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
.btn-primary { background: #007bff; } .btn-primary:hover { background: #0056b3; } ul { list-style: none; padding: 0; } ul li { margin: 10px 0; }
@media (max-width: 768px) { .sidebar, .main { width: 100%; } .sidebar { margin-bottom: 20px; } input, textarea, .btn { font-size: 14px; } }
</style></head><body><div class="header"><h1><?php echo htmlspecialchars("' . $db_name . '"); ?></h1></div><div class="container"><div class="sidebar">
<a href="' . $new_table_name . '.php" class="btn btn-primary">' . t('back') . '</a><h3>' . t('table_name') . '</h3><ul><?php foreach ($tables as $table) { echo "<li><a href=\"$table.php\" class=\"btn btn-primary\">$table</a></li>"; } ?></ul></div>
<div class="main"><h2>' . t('add_record') . '</h2><div class="form-card"><form method="post">';
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_field']) && !$col['pk']) {
                $new_col_name = $settings['columns'][$col['name']]['name'] ?? $col['name'];
                $input_type = stripos($col['type'], 'text') !== false ? 'textarea' : 'input type="text"';
                $add_content .= "<label>$new_col_name</label><$input_type name=\"{$col['name']}\" required></$input_type>";
            }
        }
        $add_content .= '<button type="submit" class="btn">' . t('add_record') . '</button></form></div></div></div></body></html>';
        file_put_contents("$output_dir/add_$table.php", $add_content);

        // صفحه ویرایش
        $edit_content = '<?php
session_start();
' . ($enable_login ? 'if (!isset($_SESSION["loggedin"])) { header("Location: login.php"); exit; }' : '') . '
$db = new PDO("sqlite:database.db");
$tables = ' . var_export($table_list, true) . ';
$translations = ' . var_export($translations, true) . ';
function t($key) { global $translations; return $translations[$_SESSION["lang"] ?? "fa"][$key] ?? $key; }
$id = $_GET["id"] ?? ""; $row = $db->query("SELECT * FROM ' . $table . ' WHERE ' . $columns[0]['name'] . ' = \'" . $id . "\'")->fetch(PDO::FETCH_ASSOC) ?? [];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = [];';
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_field'])) {
                $edit_content .= '$data["' . $col['name'] . '"] = $_POST["' . $col['name'] . '"] ?? "";';
            }
        }
        $edit_content .= '$stmt = $db->prepare("UPDATE ' . $table . ' SET ' . implode(',', array_map(fn($c) => $c['name'] . '=:' . $c['name'], $columns)) . ' WHERE ' . $columns[0]['name'] . '=\'" . $id . "\'"); 
$stmt->execute($data); header("Location: ' . $new_table_name . '.php"); exit; }
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION["lang"] ?? "fa"; ?>" dir="<?php echo ($_SESSION["lang"] ?? "fa") === "fa" ? "rtl" : "ltr"; ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . t('edit') . ' - <?php echo htmlspecialchars("' . $db_name . '"); ?></title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; }
.header { background: #007bff; color: #fff; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 10; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; display: flex; flex-wrap: wrap; }
.sidebar { width: 20%; padding: 15px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
.main { width: 75%; padding: 15px; } .form-card { background: #fff; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.btn { padding: 10px; background: #28a745; color: #fff; text-decoration: none; border-radius: 5px; width: 100%; text-align: center; transition: background 0.3s; }
.btn:hover { background: #218838; } input, textarea { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
.btn-primary { background: #007bff; } .btn-primary:hover { background: #0056b3; } ul { list-style: none; padding: 0; } ul li { margin: 10px 0; }
@media (max-width: 768px) { .sidebar, .main { width: 100%; } .sidebar { margin-bottom: 20px; } input, textarea, .btn { font-size: 14px; } }
</style></head><body><div class="header"><h1><?php echo htmlspecialchars("' . $db_name . '"); ?></h1></div><div class="container"><div class="sidebar">
<a href="' . $new_table_name . '.php" class="btn btn-primary">' . t('back') . '</a><h3>' . t('table_name') . '</h3><ul><?php foreach ($tables as $table) { echo "<li><a href=\"$table.php\" class=\"btn btn-primary\">$table</a></li>"; } ?></ul></div>
<div class="main"><h2>' . t('edit') . '</h2><div class="form-card"><form method="post">';
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_field'])) {
                $new_col_name = $settings['columns'][$col['name']]['name'] ?? $col['name'];
                $input_type = stripos($col['type'], 'text') !== false ? 'textarea' : 'input type="text"';
                $edit_content .= "<label>$new_col_name</label><$input_type name=\"{$col['name']}\" value=\"<?php echo htmlspecialchars(\$row['{$col['name']}'] ?? ''); ?>\" required></$input_type>";
            }
        }
        $edit_content .= '<button type="submit" class="btn">' . t('edit') . '</button></form></div></div></div></body></html>';
        file_put_contents("$output_dir/edit_$table.php", $edit_content);

        // صفحه حذف (با تأیید)
        $delete_content = '<?php
session_start();
' . ($enable_login ? 'if (!isset($_SESSION["loggedin"])) { header("Location: login.php"); exit; }' : '') . '
$db = new PDO("sqlite:database.db");
if (isset($_GET["id"])) {
    $id = $_GET["id"];
    $db->exec("DELETE FROM ' . $table . ' WHERE ' . $columns[0]['name'] . ' = \'" . $id . "\'");
    header("Location: ' . $new_table_name . '.php");
    exit;
}
?>
';
        file_put_contents("$output_dir/delete_$table.php", $delete_content);

        // صفحه نمایش
        $view_content = '<?php
session_start();
' . ($enable_login ? '$view_no_pass = ' . var_export($view_no_pass, true) . ';
if (!$view_no_pass && !isset($_SESSION["loggedin"])) { header("Location: login.php"); exit("<p>" . t("login_required") . "</p>"); }' : '') . '
$db = new PDO("sqlite:database.db");
$tables = ' . var_export($table_list, true) . ';
$translations = ' . var_export($translations, true) . ';
function t($key) { global $translations; return $translations[$_SESSION["lang"] ?? "fa"][$key] ?? $key; }
$row = $db->query("SELECT * FROM ' . $table . ' WHERE ' . $columns[0]['name'] . ' = \'" . ($_GET["id"] ?? "") . "\'")->fetch(PDO::FETCH_ASSOC) ?? [];
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION["lang"] ?? "fa"; ?>" dir="<?php echo ($_SESSION["lang"] ?? "fa") === "fa" ? "rtl" : "ltr"; ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . t('view') . ' - <?php echo htmlspecialchars("' . $db_name . '"); ?></title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; }
.header { background: #007bff; color: #fff; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 10; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; display: flex; flex-wrap: wrap; }
.sidebar { width: 20%; padding: 15px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px; }
.main { width: 75%; padding: 15px; } .view-card { background: #fff; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.btn { padding: 10px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px; transition: background 0.3s; } .btn:hover { background: #0056b3; }
p { margin: 10px 0; } ul { list-style: none; padding: 0; } ul li { margin: 10px 0; }
@media (max-width: 768px) { .sidebar, .main { width: 100%; } .sidebar { margin-bottom: 20px; } .btn { width: 100%; text-align: center; } }
</style></head><body><div class="header"><h1><?php echo htmlspecialchars("' . $db_name . '"); ?></h1></div><div class="container"><div class="sidebar">
<a href="' . $new_table_name . '.php" class="btn btn-primary">' . t('back') . '</a><h3>' . t('table_name') . '</h3><ul><?php foreach ($tables as $table) { echo "<li><a href=\"$table.php\" class=\"btn btn-primary\">$table</a></li>"; } ?></ul></div>
<div class="main"><h2>' . t('view') . '</h2><div class="view-card">';
        foreach ($columns as $col) {
            if (isset($settings['columns'][$col['name']]['show_field'])) {
                $new_col_name = $settings['columns'][$col['name']]['name'] ?? $col['name'];
                $view_content .= "<p><strong>$new_col_name:</strong> <?php echo htmlspecialchars(\$row['{$col['name']}'] ?? ''); ?></p>";
            }
        }
        $view_content .= '<a href="' . $new_table_name . '.php" class="btn">' . t('back') . '</a></div></div></div></body></html>';
        file_put_contents("$output_dir/view_$table.php", $view_content);
    }

    echo "<div style='text-align: center; padding: 20px;'><h3 style='color: #28a745;'>" . t('generated_system') . "</h3>
    <a href='$output_dir/index.php' style='padding: 10px 20px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px;'>" . t('system_link') . "</a></div>";
}

// منطق اصلی
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['db_file']) && isset($_FILES['db_file']['tmp_name'])) {
        if (!is_sqlite($_FILES['db_file']['tmp_name'])) die("فایل باید از نوع SQLite باشد!");
        $db_name = pathinfo($_FILES['db_file']['name'] ?? '', PATHINFO_FILENAME);
        $temp_db_path = sys_get_temp_dir() . '/' . uniqid() . '.db';
        move_uploaded_file($_FILES['db_file']['tmp_name'], $temp_db_path);
        $_SESSION['temp_db_path'] = $temp_db_path;
        $_SESSION['db_name'] = $db_name;
    } elseif (isset($_POST['create_system'])) {
        generate_system($_POST);
    }
}
if (isset($_GET['lang'])) $_SESSION['lang'] = $_GET['lang'];

// رابط کاربری اصلی
echo '<!DOCTYPE html><html lang="' . ($_SESSION['lang'] ?? 'fa') . '" dir="' . (($_SESSION['lang'] ?? 'fa') === 'fa' ? 'rtl' : 'ltr') . '">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . t('welcome') . '</title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f0f2f5; }
.header { background: #007bff; color: #fff; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 10; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.card { background: #fff; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.btn { padding: 10px 20px; text-decoration: none; color: #fff; background: #007bff; border-radius: 5px; display: inline-block; transition: background 0.3s; }
.btn:hover { background: #0056b3; } input, textarea { padding: 8px; margin: 10px 0; width: 100%; border: 1px solid #ddd; border-radius: 5px; }
.checkbox { margin: 5px; } .lang-switch { text-align: ' . (($_SESSION['lang'] ?? 'fa') === 'fa' ? 'left' : 'right') . '; margin-bottom: 10px; }
@media (max-width: 768px) { .container { padding: 10px; } .card { padding: 10px; } input, textarea, .btn { font-size: 14px; width: 100%; text-align: center; } }
</style></head><body><div class="header"><h1>' . t('welcome') . '</h1></div><div class="container"><div class="lang-switch">
<a href="?lang=en" class="btn" style="padding: 5px 10px;">EN</a><a href="?lang=fa" class="btn" style="padding: 5px 10px;">FA</a></div>
<div class="card"><h3>' . t('upload_db') . '</h3><form method="post" enctype="multipart/form-data">
<input type="file" name="db_file" required><button type="submit" class="btn">' . t('upload_db') . '</button></form></div>';

if (isset($_SESSION['temp_db_path'])) {
    $tables = get_db_structure($_SESSION['temp_db_path']);
    echo '<div class="card"><h3>' . t('db_name') . '</h3><form method="post"><input type="text" name="db_name" value="' . ($_SESSION['db_name'] ?? '') . '">';
    foreach ($tables as $table => $columns) {
        echo '<div class="card"><h3>' . t('table_name') . ': ' . $table . '</h3>
        <label>' . t('table_name') . '</label><input type="text" name="tables[' . $table . '][name]" value="' . $table . '">
        <div><label><input type="checkbox" name="tables[' . $table . '][view_without_password]" checked class="checkbox"> ' . t('view_without_password') . '</label>
        <label><input type="checkbox" name="tables[' . $table . '][show_search]" checked class="checkbox"> ' . t('show_search') . '</label>
        <label><input type="checkbox" name="tables[' . $table . '][show_table]" checked class="checkbox"> ' . t('show_table') . '</label></div>';
        foreach ($columns as $col) {
            echo '<div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;">
            <label>' . t('field_name') . '</label><input type="text" name="tables[' . $table . '][columns][' . $col['name'] . '][name]" value="' . $col['name'] . '">
            <span>(' . $col['type'] . ')</span><label><input type="checkbox" name="tables[' . $table . '][columns][' . $col['name'] . '][show_field]" checked class="checkbox"> ' . t('show_field') . '</label>';
            if (preg_match('/int|float/i', $col['type'])) {
                echo '<label><input type="checkbox" name="tables[' . $table . '][columns][' . $col['name'] . '][show_total]" class="checkbox"> ' . t('show_total') . '</label>';
            }
            echo '</div>';
        }
        echo '<label><input type="checkbox" name="tables[' . $table . '][export_excel]" class="checkbox"> ' . t('export_to_excel') . '</label></div>';
    }
    echo '<div class="card"><h3>' . t('username') . '</h3>
    <label><input type="checkbox" name="enable_login" class="checkbox"> ' . t('enable_login') . '</label><br>
    <input type="text" name="username" value="' . (isset($_POST['username']) ? htmlspecialchars($_POST['username']) : 'admin') . '">
    <label>' . t('password') . '</label><input type="password" name="password" value="' . (isset($_POST['password']) ? htmlspecialchars($_POST['password']) : 'password') . '"></div>
    <button type="submit" name="create_system" class="btn">' . t('create_system') . '</button></form></div>';
}

echo '</div></body></html>';
?>