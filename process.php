<?php

function isLatexUnit($str) {
    return preg_match("/^\d+(?:\.\d*)?(?:pt|pc|in|bp|cm|mm|dd|cc|sp|ex|em)$/m", $str);
}

function reducePageRange($range, $maxPages, $numPages) {
    $ranges = explode(",", $range);
    $newRanges = array();
    $pages = 0;
    foreach ($ranges as $r) {
        if (preg_match("/^(?:\d+|_)$/m", $r)) {
            $newRanges[] = $r;
            $pages++;
        } else {
            if (preg_match("/^(\d+)-(\d+)$/m", $r, $matches)) {
                $begin = $matches[1];
                $end = $matches[2];
            } else if (preg_match("/^(\d+)-$/m", $r, $matches)) {
                $begin = $matches[1];
                $end = $numPages;
            } else if (preg_match("/^-(\d+)$/m", $r, $matches)) {
                $begin = 1;
                $end = $matches[1];
            } else if (preg_match("/^-$/m", $r, $matches)) {
                $begin = 1;
                $end = $numPages;
            } else {
                error("SETTINGS_PAGES_PARAM_ERROR");
            }
            $pagesInRange = $end - $begin + 1;
            if ($pagesInRange > $maxPages - $pages) {
                $end = $start + ($maxPages - $pages);
                $pages = $maxPages;
                $newRanges[] = $begin . "-" . $end;
            } else {
                $newRanges[] = $r;
                $pages += $pagesInRange;
            }
        }

        if ($pages === $maxPages) {
            break;
        }
    }

    return join(",", $newRanges);
}

function error($reason) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo '{"error":"' . $reason . '"}';
    exit;
}



if (!isset($_POST['settings'])) {
    error("SETTINGS_MISSING");
}

$settings = json_decode($_POST['settings'], true);
if (!$settings) {
    error("SETTINGS_DECODE_ERROR");
}

if (!isset($settings['file']) || empty($settings['file'])) {
    error("SETTINGS_MISSING_FILE_PARAM");
}

$dir = sys_get_temp_dir();
$file = $dir . DIRECTORY_SEPARATOR . "Upload-" . $settings['file'];
if ($settings['preview']) {
    $suffix = "preview";
} else {
    $suffix = "final";
}

$options = array(
    "pdfjam",
    "--vanilla",
    "--checkfiles",
    "--outfile",
    $dir,
    "--suffix",
    $suffix,
    "--keepinfo"
);

// nup
$nup = 1;
if (isset($settings['xnup']) && is_numeric($settings['xnup'])
    && isset($settings['ynup']) && is_numeric($settings['ynup'])) {
    $options[] = "--nup";
    $options[] = $settings['xnup'] . "x" . $settings['ynup'];
    $nup = $settings['xnup'] * $settings['ynup'];
}

// landscape
if ($settings['landscape']) {
    $options[] = "--landscape";
} else {
    $options[] = "--no-landscape";
}

// paper / papersize
if (isset($settings['paper']) && !empty($settings['paper'])) {
    $paper = strtolower($settings['paper']);
    if (preg_match("/^(?:[abc][0-6]|letter|legal)$/m", $paper)) {
        $options[] = "--paper";
        $options[] = $paper . "paper";
    }
} else if (isset($settings['xpapersize']) && isLatexUnit($settings['xpapersize'])
        && isset($settings['ypapersize']) && isLatexUnit($settings['ypapersize'])) {
    $options[] = "--papersize";
    $options[] = "'{" . $settings['xpapersize'] . "," . $settings['ypapersize'] . "}'";
}

$options[] = "--";

// input file
$options[] = $file;

// page range
if (isset($settings['pages']) && !empty($settings['pages'])) {
    $pages = preg_replace("/[^\d,_-]/m", "", $settings['pages']);
} else {
    $pages = "-";
}
if (preg_match("/((?:\d*-\d*|\d+|_)(?:,(?:\d*-\d*|\d+|_))*)/m", $pages, $matches)) {
    if ($settings['preview']) {
        // reduce page range
        $maxPages = exec("pdfinfo " . $file . " | awk '/^Pages:/ {print $2}'");
        $pages = reducePageRange($matches[0], $nup, $maxPages);
    }

    $options[] = "'" . str_replace("_", "{}", $pages) . "'";
} else {
    error("SETTINGS_PAGES_PARAM_ERROR");
}

// capture full console output
$options[] = "2>&1";

$cmd = join(" ", $options);

// debug
if ($settings['debug']) {
    echo $cmd;
    exit;
}

$output = exec($cmd);
// echo  "<pre>" . $cmd . "</pre><p>output first call</p><pre>";
// print_r($output);
// echo "</pre>";

if (!$output) {
    error("CONVERSION_FAILED");
}

if (!preg_match("/^  pdfjam: Finished.  Output was written to '(\S+)'.$/m", $output, $matches)
    || !file_exists($matches[1])) {
    error("CONVERSION_FAILED");
}

if ($settings['reverse'] && !$settings['preview']) {
    $options = array(
        "pdfjam",
        "--vanilla",
        "--checkfiles",
        "--outfile",
        $dir,
        "--suffix",
        "reversed",
        "--keepinfo",
        "--",
        $matches[1],
        "'last-1'",
        "2>&1"
    );
    $cmd = join(" ", $options);
    $output = exec($cmd);
    // $res = exec($cmd, $output, $result);
    // echo  "<pre>" . $cmd . "</pre><p>output second call, result" . $result . " " . $res . "</p><pre>";;
    // print_r($output);
    // echo "</pre>";
    // exit;

    if (!$output) {
        error("INVERSION_FAILED1");
    }
    if (!preg_match("/^  pdfjam: Finished.  Output was written to '(\S+)'.$/m", $output, $matches)
        || !file_exists($matches[1])) {
        error("INVERSION_FAILED2");
    }
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($matches[1]));
readfile($matches[1]);

?>