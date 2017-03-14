<?php
/**
 * Diagnostic tool for parsing Apache access logs and displaying them in a readable
 * and filterable format.  This is intended to be single uploaded file as a
 * diagnostic tool and should not be permanently incorporated in a hosted application.
 * This tool incorporates jQuery, Bootstrap, and Datatables as external links in
 * order to maintain a single file format.
 *
 * @author David Mans <mans.david@gmail.com>
 * @copyright Copyright (c) 2017 David Mans
 * @license MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
$logView = false;
$debug = false;

set_exception_handler('exceptionHandler');

if(isset($_POST['submit']) && isset($_FILES['log']))
{
    $logLinePattern = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*\[(.*)\].*\"(GET|POST|DELETE|PUT|HEAD)\s(.*)\"\s(\d{3})\s(\d+|\-)\s\"(.*?)\"\s\"(.*?)\"/';

    //TODO Validate file type


    if(!isset($_FILES['log']['error']) || !is_numeric($_FILES['log']['error']))
    {
        throw new Exception("Invalid error structure for file");
    }

    $errorMsg = "";

    switch($_FILES['log']['error'])
    {
        case UPLOAD_ERR_OK:
            break;
        //Form max file size is set by ini max file size so they are combined
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg = "The file size exceeds the maximum file size limit.  File must be less than " . ini_get("upload_max_filesize") . ".";
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMsg = "Encountered an error attempting to upload the file.  The file was partially uploaded.";
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg = "No file was uploaded.";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMsg = "Temporary upload directory could not be located.";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMsg = "Failed to write file to disk, verify write permissions.";
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMsg = "Encountered an error with the extension of the file.";
            break;
        default:
            $errorMsg = "Encountered an undefined error while attempting to upload file.";
            break;
    }

    if(stringIsNullOrEmpty($errorMsg))
    {
        //Move uploaded file to same directory as this file
        if (!move_uploaded_file($_FILES['log']['tmp_name'], sprintf('./%s.%s', 'current', 'log')))
            throw new Exception('Encountered error attempting to move uploaded file');


        $contents = fopen('current.log', 'r');

        $lines = $entries = $rejects = [];

        while (!feof($contents))
        {
            if (fgets($contents) && !stringIsNullOrEmpty(fgets($contents)))
            {
                if (preg_match($logLinePattern, fgets($contents), $lines))
                {
                    $obj = new stdClass();

                    $obj->ip = $lines[1];
                    $obj->date = date('Y-m-d h:i:s', strtotime($lines[2]));
                    $obj->method = $lines[3];
                    $obj->resource = $lines[4];
                    $obj->status = $lines[5];
                    $obj->size = $lines[6];
                    $obj->referer = $lines[7];
                    $obj->agent = $lines[8];

                    array_push($entries, $obj);
                }
                else
                {
                    array_push($rejects, fgets($contents));
                }
            }
        }
    }

    //Encode the matches into a JSON array to display in the data table
    if(count($entries) > 0)
    {
        $json = json_encode($entries, JSON_UNESCAPED_SLASHES);
        $logView = true;
    }

    //Display any log lines that didn't conform to regex match
    if($debug && count($rejects) > 0)
    {
        echo "<pre>";
        print_r($rejects);
        echo "</pre>";
    }

}

/**
 * Determine if a string is null or empty.
 *
 * @param $string
 * @return bool
 * @throws Exception
 */
function stringIsNullOrEmpty($string)
{
    if(is_string($string) || is_null($string))
        return ($string !== "0" && (empty($string) || is_null($string))) ? true : false;
    else
        throw new Exception("Invalid data type provided, expected string");
}

/**
 * Display an uncaught exception
 *
 * @param Exception $e
 */
function exceptionHandler(Exception $e)
{
    echo "<h2>Uncaught Exception</h2>" . PHP_EOL;
    echo "<strong>Exception in " . $e->getFile() . " at " . $e->getLine() . "</strong>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>";
    echo print_r($e->getTraceAsString());
    echo "</pre>";
}

?>

<!doctype html>
<html>
<head>
    <title>Log Parser</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs/dt-1.10.13/datatables.min.css"/>
</head>
<body>
<div class="container">
    <section>
    <div class="row">
        <form enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
            <div class="form-group">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo rtrim(ini_get('upload_max_filesize'), 'M') * 1000000?>" />
                <label>Choose a file to upload:</label><input class="form-control" id='logfile' name="log" type="file" /><br />
            </div>
            <input class="form-control" type="submit" name="submit" value="Upload File" />
        </form>
    </div>
    </section>
<?php if($logView): ?>
    <div class="row" style="margin-top: 15px">
        <table class="table" id="logEntries" class="display" width="100%"></table>
    </div>
<?php endif; ?>
</div>
<script
    src="https://code.jquery.com/jquery-3.1.1.min.js"
    integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
    crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs/dt-1.10.13/datatables.min.js"></script>
<script>
    $(document).ready(function() {

        var entries = <?php echo $json ?>;

        $('#logEntries').DataTable({
            data: entries,
            columns: [
                { title: "IP", data: "ip" },
                { title: "Date", data: "date" },
                { title: "Method", data: "method" },
                { title: "Resource", data: "resource" },
                { title: "Status", data: "status" },
                { title: "Referer", data: "referer" },
                { title: "Agent", data: "agent" }
            ]
        });
    });
</script>
</body>
</html>

