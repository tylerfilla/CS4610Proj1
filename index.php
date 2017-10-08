<?php

/*
 * Tyler Filla
 * CS 4610
 * Project 1
 */

$param_act = $_POST["act"];
$param_pid = $_POST["pid"];
$param_page = $_GET["page"];
$param_psize = $_GET["psize"];

$page = $param_page ?: 1;
$page_size = $param_psize ?: 20;

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
// These are order indexes, not problem IDs (they are reassigned as problems move around)
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

// Calculate number of last page
$last_page = ceil(count($ordered_pids) / $page_size);

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
        <script type="text/javascript">
            //
            // Problem Action Functions
            //

            var action_form;
            var action_form_act;
            var action_form_pid;

            function find_action_form() {
                action_form = action_form || document.getElementById("action_form");
                action_form_act = action_form_act || document.getElementById("action_form_act");
                action_form_pid = action_form_pid || document.getElementById("action_form_pid");
            }

            function do_action(act, pid) {
                find_action_form();
                action_form_act.value = act;
                action_form_pid.value = pid;
                action_form.submit();
            }

            //
            // Page Control Functions
            //

            // FIXME: XSS vulnerabilities abound
            var current_page = <?php echo $page; ?>;
            var current_page_size = <?php echo $page_size;?>;
            var last_page = <?php echo $last_page; ?>;

            function go_first_page() {
                window.location = "?page=1&psize=" + current_page_size;
            }

            function go_previous_page() {
                if (current_page === 1)
                    return;

                window.location = "?page=" + (current_page - 1) + "&psize=" + current_page_size;
            }

            function go_next_page() {
                if (current_page === last_page)
                    return;

                window.location = "?page=" + (current_page + 1) + "&psize=" + current_page_size;
            }

            function go_last_page() {
                window.location = "?page=" + last_page + "&psize=" + current_page_size;
            }
        </script>
        <link rel="stylesheet" type="text/css"
              href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"/>
        <style type="text/css">
            body {
                font-family: sans-serif;
            }

            #container {
                width: 75%;
                margin-left: 12.5%;
                margin-right: 12.5%;
            }

            #compose-form-content {
                width: 100%;
            }

            #problem-table {
                border: 1px solid black;
                border-collapse: collapse;

                text-align: center;

                width: 100%;
            }

            #problem-table td {
                border: 1px solid black;
                padding: 10px;
            }

            #problem-table tr.highlight {
                background-color: #eaeaea;
            }

            #problem-table .header {
                font-weight: bold;
            }

            #problem-table .content {
                font-size: 75%;
                text-align: left;
            }

            #problem-table .actions {
                width: 15%;
            }
        </style>
    </head>
    <body>
    <div id="container">
        <div class="row" style="text-align: center;">
            <h1>Math Question Bank</h1>
            <h4>CS 4610 Project 1</h4>
            <h4>Tyler Filla</h4>
        </div>
        <div class="row" style="margin-bottom: 20px; margin-top: 20px;">
            <form method="post">
                <label for="compose-form-content">Submit a new problem:</label><br/>
                <textarea id="compose-form-content" name="content" rows="5"></textarea>
                <input type="hidden" name="act" value="compose"/><br/>
                <button type="submit"><span class='glyphicon glyphicon-ok'></span> Submit problem</button>
            </form>
        </div>
        <div class="row">
            <label for="problem-table">Problems in bank:</label>
            <div style="text-align: center; margin-bottom: 5px;">
                <button onclick="go_first_page();"><span class='glyphicon glyphicon-step-backward'></span></button>
                <button onclick="go_previous_page();"><span class='glyphicon glyphicon-chevron-left'></span></button>
                &nbsp;<?php echo "Page $page of $last_page"; ?>&nbsp;<!-- FIXME: XSS -->
                <button onclick="go_next_page();"><span class='glyphicon glyphicon-chevron-right'></span></button>
                <button onclick="go_last_page();"><span class='glyphicon glyphicon-step-forward'></span></button>
            </div>
            <table id="problem-table">
                <tr class="header">
                    <td>Order</td>
                    <td>ID</td>
                    <td>Content</td>
                    <td>Actions</td>
                </tr>
                <?php
                // Scan problems in the user's preferred order
                for ($num = $problem_first; $num <= $problem_last; ++$num) {
                    // Get problem ID and content
                    $pid = $ordered_pids[$num - 1];

                    // Stop when we run out of problems
                    if (!$pid)
                        break;

                    // Query the database for the desired problems' content
                    // FIXME: SQL injection
                    $sql_result_content = $sql_conn->query("SELECT `content` FROM `problem` WHERE `pid` = $pid");
                    if (!$sql_result_content) {
                        die("database query failed");
                    }

                    // Fetch content
                    $content = $sql_result_content->fetch_all(MYSQLI_ASSOC)[0]["content"];
                    $sql_result_content->free_result();

                    // Begin table row (highlight row if it was just acted upon)
                    echo "<tr" . ($pid == $param_pid ? " class='highlight'" : "") . ">";

                    // Order number, problem ID, and problem content
                    // FIXME: XSS vulnerabilities here
                    echo "<td style='width: 5%;'>$num</td>";
                    echo "<td style='width: 5%;'>$pid</td>";
                    echo "<td class='content'>$content</td>";

                    // Problem action buttons
                    // FIXME: XSS here, too
                    echo "<td class='actions' style='width: 12.5%;'>";
                    echo "<button onclick='do_action(\"up\", $pid)'><span class='glyphicon glyphicon-chevron-up'></span></button>";
                    echo "<button onclick='do_action(\"down\", $pid)'><span class='glyphicon glyphicon-chevron-down'></span></button>";
                    echo "<br />";
                    echo "<button onclick='do_action(\"edit\", $pid)'><span class='glyphicon glyphicon-pencil'></span></button>";
                    echo "<button onclick='do_action(\"trash\", $pid)'><span class='glyphicon glyphicon-trash'></span></button>";
                    echo "</td>";

                    // End table row
                    echo "</tr>";
                }
                ?>
            </table>
        </div>
    </div>
    <form id="action_form" method="post" style="display: none;">
        <input type="hidden" id="action_form_act" name="act"/>
        <input type="hidden" id="action_form_pid" name="pid"/>
    </form>
    </body>
    </html>
<?php
// Close the database connection
$sql_conn->close();
?>