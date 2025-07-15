<?php

// ==== Ініціалізація ====
require_once('config.php');
require_once(DIR_SYSTEM . 'startup.php');

$registry = new Registry();
$loader = new Loader($registry);
$registry->set('load', $loader);
$loader->model('catalog/product');
$loader->model('setting/setting');
$loader->model('catalog/category');
$loader->model('tool/image');
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

// ==== Налаштування ====
$dry_run = true; // true — лише лог, false — перейменовує

$scan_folder = $argv[1] ?? 'catalog/';
$recursive_scan = isset($argv[2]) && strtolower($argv[2]) === 'true';
$log_file = DIR_LOGS . 'image_rename.log';
$language_id = 2; // 1 — укр, 2 — рос.

// ==== Логування ====
function logMessage($message) {
    global $log_file;
    file_put_contents($log_file, $message . PHP_EOL, FILE_APPEND);
}

// ==== Транслітерація ====
function transliterate($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'i',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch',
        'ш'=>'sh','щ'=>'shch','ю'=>'yu','я'=>'ya','ь'=>'','’'=>'','ы'=>'y','э'=>'e',
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'H','Ґ'=>'G','Д'=>'D','Е'=>'E','Є'=>'Ye','Ж'=>'Zh','З'=>'Z','И'=>'Y','І'=>'I','Ї'=>'Yi','Й'=>'I',
        'К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch',
        'Ш'=>'Sh','Щ'=>'Shch','Ю'=>'Yu','Я'=>'Ya','Ь'=>'','Ы'=>'Y','Э'=>'E'
    ]);
    $text = preg_replace('/[^A-Za-z0-9\- ]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

// ==== Генерація унікального імені ====
function getAvailableFilename($directory, $basename, $extension, &$used) {
    $i = 0;
    $filename = $basename . $extension;
    while (in_array(strtolower($filename), $used) || file_exists($directory . '/' . $filename)) {
        $filename = $basename . '-' . (++$i) . $extension;
    }
    $used[] = strtolower($filename);
    return $filename;
}

// ==== Старт ====
$full_dir = rtrim(DIR_IMAGE, '/') . '/' . trim($scan_folder, '/');

logMessage("==== Старт: " . date('Y-m-d H:i:s') . " | Папка: $full_dir | Рекурсія: " . ($recursive_scan ? 'ON' : 'OFF') . " ====");

$found = false;
$used_names = [];

$files = [];

if (!is_dir($full_dir)) {
    logMessage("❌ Вказана папка не існує: $full_dir");
    exit("Папка не знайдена\n");
}

// ==== Отримати список файлів ====
if ($recursive_scan) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full_dir));
    foreach ($rii as $file) {
        if ($file->isFile()) {
            $files[] = $file;
        }
    }
} else {
    foreach (glob($full_dir . '/*') as $filename) {
        if (is_file($filename)) {
            $files[] = new SplFileInfo($filename);
        }
    }
}

// ==== Основна логіка ====
foreach ($files as $file) {
    $full_path = $file->getPathname();
    $relative_path = str_replace(DIR_IMAGE, '', $full_path);

    // --- Головне зображення ---
    $query = $db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE image = '" . $db->escape($relative_path) . "'");
    if ($query->num_rows) {
        $row = $query->row;
        $product_query = $db->query("SELECT name FROM " . DB_PREFIX . "product_description WHERE product_id = " . (int)$row['product_id'] . " AND language_id=" . (int)$language_id . " LIMIT 1");

        if (!$product_query->num_rows) continue;

        $name = transliterate($product_query->row['name']);
        $name = substr($name, 0, 100);

        $new_filename = getAvailableFilename($file->getPath(), $name, '.' . $file->getExtension(), $used_names);
        $new_full_path = $file->getPath() . '/' . $new_filename;
        $new_relative_path = str_replace(DIR_IMAGE, '', $new_full_path);

        logMessage("🔍 " . ($dry_run ? "DRY-RUN" : "RENAMING") . ": $relative_path → $new_relative_path");

        if (!$dry_run) {
            rename($full_path, $new_full_path);
            $db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $db->escape($new_relative_path) . "' WHERE product_id = " . (int)$row['product_id']);
        }

        $found = true;
        continue;
    }

    // --- Додаткове зображення ---
    $query = $db->query("SELECT product_id FROM " . DB_PREFIX . "product_image WHERE image = '" . $db->escape($relative_path) . "'");
    if ($query->num_rows) {
        $row = $query->row;
        $meta_query = $db->query("SELECT meta_title FROM " . DB_PREFIX . "product_description WHERE product_id = " . (int)$row['product_id'] . " AND language_id=" . (int)$language_id . " LIMIT 1");

        if (!$meta_query->num_rows) continue;

        $meta = transliterate($meta_query->row['meta_title']);
        $meta = substr($meta, 0, 55);

        $new_filename = getAvailableFilename($file->getPath(), $meta, '.' . $file->getExtension(), $used_names);
        $new_full_path = $file->getPath() . '/' . $new_filename;
        $new_relative_path = str_replace(DIR_IMAGE, '', $new_full_path);

        logMessage("🔍 " . ($dry_run ? "DRY-RUN" : "RENAMING") . ": $relative_path → $new_relative_path");

        if (!$dry_run) {
            rename($full_path, $new_full_path);
            $db->query("UPDATE " . DB_PREFIX . "product_image SET image = '" . $db->escape($new_relative_path) . "' WHERE product_id = " . (int)$row['product_id'] . " AND image = '" . $db->escape($relative_path) . "'");
        }

        $found = true;
    }
}

if (!$found) {
    logMessage("ℹ️ Не знайдено файлів для перейменування.");
}

echo "✅ Завершено!\n";
logMessage("🎉 Завершено.\n");
