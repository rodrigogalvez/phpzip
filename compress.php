<?php

header('Content-Type: text/plain');

// Cambia el formato de files para que sea más sencillo de procesar.
$files = reArrayFiles($_FILES["files"]);

// Crea el archivo ZIP
$zip = new ZipArchive;
$zipname = tempnam(sys_get_temp_dir(), "zip");

if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
    // Sin errores, se lee y comprime cada archivo correctamente cargado
    foreach ($files as $file) {
        if ($file["error"] === UPLOAD_ERR_OK)
            $zip->addFromString($file["name"], file_get_contents($file["tmp_name"]));
    }
    // Cierra el archivo ZIP y lo envía como respuesta
    $zip->close();
    header('Content-Type: application/x-download');
    header('Content-Disposition: attachment; filename="archive.zip"');
    readfile($zipname);
    unlink($zipname);
} else {
    // En caso de error envía un texto de error.
    echo "ERROR";
}

function reArrayFiles($file_post)
{

    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);

    for ($i = 0; $i < $file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }

    return $file_ary;
}

?>