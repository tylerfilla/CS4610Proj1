<?php

define("DEFAULT_PAGE", 0);
define("DEFAULT_PAGE_SIZE", 20);

$page = $_GET["page"] ?: DEFAULT_PAGE;
$page_size = $_GET["psize"] ?: DEFAULT_PAGE_SIZE;

// Basic validation of page number
if (gettype($page) != "integer" || $page < 0) {
    http_response_code(400);
    exit("invalid page number");
}

// Basic validation of page size
if (gettype($page_size) != "integer" || $page_size < 0) {
    http_response_code(400);
    exit("invalid page size");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Math Question Bank</title>
    <link rel="stylesheet" type="text/css" href="/static/style/page_list.css" />
</head>
<body>
</body>
</html>
