# phpzip

Comprime archivos por drag and drop o por carga directa usando *ZipArchive*

## Archivos

* Archivo *draganddrop.html*: presenta una pantalla con un recuadro para arrastrar archivos desde el escritorio. Muestra los archivos cargados con el botón de más abajo, los envía al servidor. La respuesta del servidor es un archivo comprimido en ZIP con los mismos archivos cargados.
* Archivo *flat.html*: presenta una pantalla con un *input type="file" multiple* para cargar varios archivos a la vez. Una vez cargados, se envían al sevidor con el botón de más abajo. La respuesta del servidor es un archivo comprimido en ZIP con los mismos archivos cargados.
* Archivo *compress.php*: es el archivo del servidor que recibe los requerimientos de los dos archivos anteriores. El programa recibe los archivos y los graba en un archivo comprimido en formato ZIP.

## Modo de uso

Habilite la extensión ZIP en *php.ini*:

```ini
extension=zip
```

Recomiendo ejecutar con el servidor de PHP local:

```dos
php -s localhost:8000
```

Luego siga uno de estos dos pasos:

1. *Drag And Drop*
    1. Abra el navegador con la URL *<http://localhost:8000/dragandrop.html>*
    1. Abra una carpeta de su computadora y elija algunos archivos para comprimir.
    1. Arrastre los archivos al recuadro azul del navegador.
    1. Presione el botón *Comprimir*.
    1. Guarde y revise el archivo comprimido recibido como respuesta.
1. *Flat*
    1. Abra el navegadir con la URL *<http://localhost:8000/flat.html>*
    1. Seleccione varios archivos desde el botón.
    1. Presione el botón *Comprimir*
    1. Guarde y revise el archivo comprimido recibido como respuesta.

## Explicación

En ambos casos la compresión la realiza el programa *compress.php*. Este programa espera que se envíen archivos usando el método POST en una variable llamada *files*. La variable *$_FILES["files"]* de PHP guarda información sobre los archivos enviados, pero es um poco complicada de usar. La subrutina *reArrayFiles* ordena la información de la variable *$_FILES["files"]* para que sea más sencillo de acceder.

```php
$files = reArrayFiles($_FILES["files"]);
```

Los archivos cargados son leídos directamente en memoria mediante *file_get_contents* y son agregados a un archivo comprimido temporal usando la clase *ZipArchive*.

```php
$zip = new ZipArchive;
$zipname = tempnam(sys_get_temp_dir(), "zip");
foreach ($files as $file) {
    if ($file["error"] === UPLOAD_ERR_OK)
        $zip->addFromString($file["name"], file_get_contents($file["tmp_name"]));
}
$zip->close();
```

La clase *ZipArchive* está disponible habilitando la extensión ZIP en *php.ini*:

```ini
extension=zip
```

Si no hay errores, el archivo se envía como respuesta del script con el nombre predeterminado *archive.zip*. El navegador reconocerá que es un adjunto gracias al header *Content-Type: application/x-download*.

```php
header('Content-Type: application/x-download');
header('Content-Disposition: attachment; filename="archive.zip"');
readfile($zipname);
unlink($zipname);
```

El archivo *flat.html* presenta un formulario sencillo donde pueden seleccionar varios archivos para comprimir. El formulario invoca al archivo *compres.php* recién descrito para realizar la compresión. Para seleccionar varios archivos a la vez se debe indicar un *input type file* con un nombre con corchetes (*file[]*) y el modificador *multiple*.

```html
<form action="compress.php" method="post" enctype="multipart/form-data">
    Seleccionar varios archivos:<br />
    <input name="files[]" type="file" multiple /><br />
    <input type="submit" value="Comprimir" />
</form>
```

El archivo *dragandrop.html* es más complejo ya que funciona como una SPA (Single Page Application) cargando los archivos a memoria, enviandolos al servidor y capturando la respuesta sin recargar la página.

Un div llamado *drop_zone* se utiliza para recibir los archivos.

```html
<div id="drop_zone" ondrop="dropHandler(event);" ondragover="dragOverHandler(event);">
    <p>Arrastre los archivos a comprimir a este lugar.</p>
</div>
```

Un div llamado *file_list* muestra los archivos que serán comprimnidos. En principio, es un div vacío.

```html
<div id="file_list"></div>
```

Otro div llamado *message* muestra mensajes de error si los hubiere. También en principio está vacío.

```html
<div id="message"></div>
```

De las funciones implementadas en JavaScript, la más importante es la función *post*. Esta prepara los archivos cargados en memoria para envairlos al servidor como si se enviaran usando el formulario del archivo *flat.html*. Para esto, crea un objeto *FormData* al que se le agrega una variable llamada *file[]*. El contenido del archivo se adjunta mediante un objeto *blob*.

```javascript
let formData = new FormData();
data.forEach(file => {
    let data = atob(file.data.slice(file.data.indexOf(",") + 1));
    let bytes = new Uint8Array(data.length);
    for (let i = 0; i < data.length; i++) {
        bytes[i] = data.charCodeAt(i);
    }
    let blob = new Blob([bytes, file.type]);

    formData.append("files[]", blob, file.name);
})
```

El formulario es enviado al servidor usando la API *fetch*.

```javascript
fetch("compress.php", {
    method: "POST",
    body: formData
})
```

Esta API devuelve una promesa. En el código fuente se analizan varias alternativas de respuesta, como texto o JSON, pero en esta documentación se presenta solo la parte que analiza la recepción del archivo comprimido desde el servidor.

```javascript
.then(function (response) {
    let contentType = response.headers.get("content-type");
    if (contentType.includes("application/x-download")) {
        response.blob().then(blob => {
            console.log(blob);
            download("archive.zip", blob, contentType);
            resolve(blob);
        });
    } 
    // Nota: otras alternativas omitidas.
})
```

Notar que para descargar el archivo, se debe simular que se ha hecho un clic en un elemento *A HREF*. Esto lo resuelve la función *download* creando una URL para el *blob* y luego invocando el clic. Esto no funciona en todos los navegadores aunque en general para Safari, se debe abrir la URL directamente mientras que en otros navegadores se simula el clic en el objeto *A HREF*.

```javascript
function download(filename, blob) {

    let dataURL = window.URL.createObjectURL(blob);

    if (window.navigator.userAgent.match(/iPad|iPhone/i)) {
        // iPad or iPhone
        window.open(dataURL);
    } else {
        // Anything else
        let aref = document.createElementNS("http://www.w3.org/1999/xhtml", "a")
        aref.href = dataURL;
        aref.download = filename;
        aref.click();
    }

}
```

## Texto seguro

Me importa destacar que una práctica de seguridad es que al presentar un texto se debe formatear para que no se interpreten caracteres especiales como parte del código HTML. Esto se resuelve usando nodos de texto. Por esto en el código fuente el listado de archivos y los mensajes de error no se escriben directamente sino a través del método *document.createTextNode*. Otros elementos se deben crear y anidar garantizando así el texto HTML consistente. Como ejemplo de esto, la función *updateList* que presenta el listado de archivos que serán enviados al servidor utiliza los métodos *document.createElement* y *document.createTextNode* para garantizar la consistencia y seguridad del código HTML.

```javascript
function createLI(...elements) {
    let li = document.createElement("li");
    (elements || []).flat(2).forEach(element => {
        li.appendChild(element);
    })
    return li;
}

function createUL(...elements) {
    let ul = document.createElement("ul");
    (elements || []).flat(2).forEach(element => {
        ul.appendChild(element);
    })
    return ul;
}

function updateList() {
    let fl = document.getElementById("file_list");
    fl.innerHTML = "";
    fl.appendChild(
        createUL(
            mem.map(element => {
                return createLI(
                    document.createTextNode(element.name),
                    createUL(
                        createLI(document.createTextNode(element.size)),
                        createLI(document.createTextNode(element.type)),
                        createLI(document.createTextNode(element.ok ? "OK" : "Error"))
                    )
                );
            })));
}
```




## Referencias

- [Subrutina reArrayFiles](https://www.php.net/manual/en/features.file-upload.multiple.php#53240)
- [Página web con Drag'n Drop desde el escritorio](https://developer.mozilla.org/en-US/docs/Web/API/HTML_Drag_and_Drop_API/File_drag_and_drop)