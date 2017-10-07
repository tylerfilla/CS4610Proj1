<?php

/*
 * Tyler Filla
 * CS 4610
 * Project 1
 */

$page = $_GET["page"] ?: 1;
$page_size = $_GET["psize"] ?: 20;

// Basic validation of page number
if (!is_numeric($page) || $page <= 0) {
    http_response_code(400);
    exit("invalid page number");
}

// Basic validation of page size
if (!is_numeric($page_size) || $page_size <= 0) {
    http_response_code(400);
    exit("invalid page size");
}

// Calculate inclusive range of desired problems (1-indexed)
$problem_first = ($page - 1) * $page_size + 1;
$problem_last = $problem_first + $page_size - 1;

if ($problem_last < $problem_first) {
    http_response_code(400);
    exit;
}

// Open a database connection
$sql_conn = new mysqli("localhost", "root", "thisisthepassword", "mathprobdb");
if ($sql_conn->connect_errno) {
    die("database open failed");
}

// Query the database for problem order information
$sql_result_order = $sql_conn->query("SELECT `pid`, `follows` FROM `problem_order`;");
if (!$sql_result_order) {
    die("database query failed");
}

// Build array of problem IDs indexed by those they follow
// To find the problem that follows problem X, look at $problem[X]
$pids_by_follows = array();
while ($sql_row = $sql_result_order->fetch_assoc()) {
    $pids_by_follows[$sql_row["follows"]] = $sql_row["pid"];
}
$sql_result_order->free_result();

// Array of problem IDs, ordered by user preference
$ordered_pids = array();

// Trace problem ordering (between the DB and this PHP script, a linked list of sorts is built)
// E.g. What follows X? Y. What follows Y? Z. What follows Z? etc.
$pid = 0;
while ($pid = $pids_by_follows[$pid]) {
    $ordered_pids[] = $pid;
}

// Query the database for the desired problems' content
$sql_result_content = $sql_conn->query("SELECT `pid`, `content` FROM `problem` WHERE `pid`");
if (!$sql_result_content) {
    die("database query failed");
}

// Fetch content rows
// We'll save these for later
$sql_rows_content = $sql_result_content->fetch_all(MYSQLI_ASSOC);
$sql_result_content->free_result();

// Close the database connection
$sql_conn->close();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Math Question Bank</title>
    <script type="text/javascript">
        MathJax.Hub.Config({
            tex2jax: {inlineMath: [['$', '$'], ['\\(', '\\)']]}
        });
    </script>
    <script type="text/javascript"
            src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.2/MathJax.js?config=TeX-MML-AM_CHTML"></script>
    <style type="text/css">
        #problem-table
        {
            border: 1px solid black;
        }
    </style>
</head>
<body>
<h1>Math Question Bank</h1>
<table id="problem-table">
    <?php
    foreach ($ordered_pids as $pid) {
        if ($pid < $problem_first || $pid > $problem_last)
            continue;

        $content = $sql_rows_content[$pid - 1]["content"];
        echo "<tr><td>$pid</td><td>$content</td></tr>";
    }
    ?>
</table>
</body>
</html>
