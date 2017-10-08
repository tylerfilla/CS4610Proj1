<?php

/*
 * Tyler Filla
 * CS 4610
 * Project 1
 */

//
// Parameter Input & Validation
//

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

//
// Database Connection
//

// Open a database connection
$sql_conn = new mysqli("localhost", "root", "thisisthepassword", "mathprobdb");
if ($sql_conn->connect_errno) {
    die("database open failed");
}

//
// User Action Functionality
//

/**
 * Perform the compose action.
 *
 * @param $sql_conn \mysqli The mysqli connection
 *
 * @return int The new problem's ID
 */
function act_compose($sql_conn)
{
    // Get action parameters
    $content = $_POST["content"];

    // Insert content to database
    // We temporarily set follows to something nonzero as a hack (I chose 1)
    $result = $sql_conn->query("INSERT INTO `problem` (`content`, `follows`) VALUES ('$content', 1);");
    if (!$result) {
        die("compose failed: insert content");
    }

    // Query auto-assigned ID of new problem
    $result = $sql_conn->query("SELECT LAST_INSERT_ID();");
    if (!$result) {
        die("compose failed: get ID");
    }
    $pid = $result->fetch_all()[0][0];

    // Make former first problem follow the new problem
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = $pid WHERE `follows` = 0;");
    if (!$result) {
        die("compose failed: reorder step 2");
    }

    // Now make the new problem first
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = 0 WHERE `pid` = $pid;");
    if (!$result) {
        die("compose failed: reorder step 3");
    }

    return $pid;
}

/**
 * Perform the up action.
 *
 * @param $sql_conn \mysqli The mysqli connection
 */
function act_up($sql_conn)
{
    // Get action parameters
    $pid = $_POST["pid"];

    // Get target problem's follows
    $result = $sql_conn->query("SELECT `follows` FROM `problem` WHERE `pid` = $pid;");
    if (!$result) {
        die("move up failed: get target follows");
    }
    $follows = $result->fetch_assoc()["follows"];

    // Get target problem's follower
    $result = $sql_conn->query("SELECT `pid` FROM `problem` WHERE `follows` = $pid;");
    if (!$result) {
        die("move up failed: get target follower");
    }
    $follower = $result->fetch_assoc()["pid"];

    // Stop if problem is already first
    if ($follows == 0)
        return;

    // Make target follow target follows' follows
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = (SELECT `follows` FROM (SELECT * FROM `problem`) AS temp WHERE `pid` = $follows) WHERE `pid` = $pid;");
    if (!$result) {
        die("move up failed: exclude preceding: $sql_conn->error");
    }

    // Make target follows follow target
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = $pid WHERE `pid` = $follows;");
    if (!$result) {
        die("move up failed: reroute preceding: $sql_conn->error");
    }

    // Make target follower follow target follows
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = $follows WHERE `pid` = $follower;");
    if (!$result) {
        die("move up failed: reroute follower: $sql_conn->error");
    }
}

/**
 * Perform the down action.
 *
 * @param $sql_conn \mysqli The mysqli connection
 */
function act_down($sql_conn)
{
    // Get action parameters
    $pid = $_POST["pid"];
}

/**
 * Perform the edit action.
 *
 * @param $sql_conn \mysqli The mysqli connection
 */
function act_edit($sql_conn)
{
    // Get action parameters
    $pid = $_POST["pid"];
    $content = $_POST["content"];

    // Update problem content
    $result = $sql_conn->query("UPDATE `problem` SET `content` = '$content' WHERE `pid` = $pid;");
    if (!$result) {
        die("edit failed: update content");
    }
}

/**
 * Perform the trash action.
 *
 * @param $sql_conn \mysqli The mysqli connection
 */
function act_trash($sql_conn)
{
    // Get action parameters
    $pid = $_POST["pid"];

    // Get the problem that the target problem follows
    $result = $sql_conn->query("SELECT `follows` FROM `problem` WHERE `pid` = $pid;");
    if (!$result) {
        die("trash failed: get follows");
    }
    $follows = $result->fetch_assoc()["follows"];

    // Exclude target problem from linked list
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = $follows WHERE `follows` = $pid;");
    if (!$result) {
        die("trash failed: exclude");
    }

    // Make target problem follow nothing else in the list
    // It will be completely disconnected from every other problem
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = -1 WHERE `pid` = $pid;");
    if (!$result) {
        die("trash failed: isolate");
    }

    // Update trashed timestamp on target problem
    $result = $sql_conn->query("UPDATE `problem` SET `trashed` = NOW() WHERE `pid` = $pid;");
    if (!$result) {
        die("trash failed: set trashed timestamp");
    }
}

/**
 * Perform the undo action.
 *
 * @param $sql_conn \mysqli The mysqli connection
 */
function act_undo($sql_conn)
{
    // Get the ID of the last problem to be moved to the trash
    $result = $sql_conn->query("SELECT `pid` FROM `problem` WHERE `trashed` = (SELECT MAX(`trashed`) FROM `problem`);");
    if (!$result) {
        die("undo failed: get last trashed");
    }
    $next_pid = $result->fetch_assoc()["pid"];

    // Make former first problem follow the to-be-restored problem
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = $next_pid WHERE `follows` = 0;");
    if (!$result) {
        die("undo failed: reinstate order step 1");
    }

    // Now make the to-be-restored problem first
    $result = $sql_conn->query("UPDATE `problem` SET `follows` = 0 WHERE `pid` = $next_pid;");
    if (!$result) {
        die("undo failed: reinstate order step 2");
    }

    // Clear the now-restored problem's trashed timestamp
    $result = $sql_conn->query("UPDATE `problem` SET `trashed` = NULL WHERE `pid` = $next_pid;");
    if (!$result) {
        die("undo failed: clear trashed timestamp");
    }
}

/**
 * Perform the empty trash action.
 *
 * @param $sql_conn \mysqli The mysqli connection
 */
function act_empty_trash($sql_conn)
{
    $result = $sql_conn->query("DELETE FROM `problem` WHERE `follows` = -1;");
    if (!$result) {
        die("empty trash failed: delete");
    }
}

switch ($param_act) {
case "compose":
    $param_pid = act_compose($sql_conn);
    break;
case "up":
    act_up($sql_conn);
    break;
case "down":
    act_down($sql_conn);
    break;
case "edit":
    act_edit($sql_conn);
    break;
case "trash":
    act_trash($sql_conn);
    break;
case "undo":
    act_undo($sql_conn);
    break;
case "etrash":
    act_empty_trash($sql_conn);
    break;
}

//
// Problem List Functionality
//

// Query the database for problem order information
$sql_result_order = $sql_conn->query("SELECT `pid`, `follows` FROM `problem`;");
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
        // Problem Composition Functions
        //

        function on_compose_form_submit() {
            var compose_form_content = document.getElementById("compose-form-content");
            var content = compose_form_content.value;

            if (content === "") {
                alert("Content may not be empty!");
                return false;
            }

            return true;
        }

        //
        // Problem Action Functions
        //

        function submit_action(act, pid, content) {
            var action_form = document.getElementById("action-form");
            var action_form_act = document.getElementById("action-form-act");
            var action_form_pid = document.getElementById("action-form-pid");
            var action_form_content = document.getElementById("action-form-content");

            action_form_act.value = act;
            action_form_pid.value = pid;
            action_form_content.value = content;
            action_form.submit();
        }

        function act_up(pid) {
            submit_action("up", pid, null);
        }

        function act_down(pid) {
            submit_action("down", pid, null);
        }

        function act_edit(pid) {
            var actions_normal = document.getElementById("actions-normal-" + pid);
            var actions_edit = document.getElementById("actions-edit-" + pid);
            var content_normal = document.getElementById("content-normal-" + pid);
            var content_edit = document.getElementById("content-edit-" + pid);

            actions_normal.style.display = "none";
            actions_edit.style.display = "";
            content_normal.style.display = "none";
            content_edit.style.display = "";
        }

        function act_trash(pid) {
            submit_action("trash", pid, null);
        }

        function act_undo() {
            submit_action("undo", 0, null);
        }

        function act_empty_trash() {
            submit_action("etrash", 0, null);
        }

        function edit_accept(pid) {
            var actions_normal = document.getElementById("actions-normal-" + pid);
            var actions_edit = document.getElementById("actions-edit-" + pid);
            var content_normal = document.getElementById("content-normal-" + pid);
            var content_edit = document.getElementById("content-edit-" + pid);
            var content_edit_text = document.getElementById("content-edit-" + pid + "-text");

            var content = content_edit_text.value;

            if (content === "") {
                alert("Content may not be empty!");
                return;
            }

            actions_normal.style.display = "";
            actions_edit.style.display = "none";
            content_normal.style.display = "";
            content_edit.style.display = "none";

            submit_action("edit", pid, content);
        }

        function edit_reject(pid) {
            var actions_normal = document.getElementById("actions-normal-" + pid);
            var actions_edit = document.getElementById("actions-edit-" + pid);
            var content_normal = document.getElementById("content-normal-" + pid);
            var content_edit = document.getElementById("content-edit-" + pid);

            actions_normal.style.display = "";
            actions_edit.style.display = "none";
            content_normal.style.display = "";
            content_edit.style.display = "none";
        }

        //
        // Page Control Functions
        // FIXME: XSS vulnerabilities abound
        //

        function go_first_page() {
            window.location = "?page=1&psize=<?php echo $page_size; ?>";
        }

        function go_previous_page() {
            <?php echo $page == 1 ? "//" : "" ?>
            window.location = "?page=<?php echo $page - 1; ?>&psize=<?php echo $page_size; ?>";
        }

        function go_next_page() {
            <?php echo $page == $last_page ? "//" : "" ?>
            window.location = "?page=<?php echo $page + 1; ?>&psize=<?php echo $page_size; ?>";
        }

        function go_last_page() {
            window.location = "?page=<?php echo $last_page; ?>&psize=<?php echo $page_size; ?>";
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
        <form method="post" onsubmit="return on_compose_form_submit();">
            <label for="compose-form-content">Submit a new problem:</label><br/>
            <textarea id="compose-form-content" name="content" rows="5"></textarea>
            <input type="hidden" name="act" value="compose"/><br/>
            <button type="submit"><span class='glyphicon glyphicon-ok'></span> Submit problem</button>
        </form>
    </div>
    <div class="row">
        <label for="problem-table">Problems in bank:</label>
        <?php
        $sql_result_trash = $sql_conn->query("SELECT COUNT(*) FROM `problem` WHERE `trashed` IS NOT NULL;");
        if (!$sql_result_trash) {
            die("trash query failed");
        }
        $num_trashed = $sql_result_trash->fetch_all()[0][0];

        if ($num_trashed > 0) {
            echo "<div style='text-align: center; margin-bottom: 5px; padding: 5px; background-color: #eaeaea;'>";
            echo "<label>$num_trashed problem" . ($num_trashed == 1 ? " is in" : "s are") . " in the trash.</label><br />";
            echo "<button onclick='act_undo()'><span class='glyphicon glyphicon-backward'></span> Undo</button>";
            echo "<button onclick='act_empty_trash()'><span class='glyphicon glyphicon-trash'></span> Empty trash</button>";
            echo "</div>";
        }
        ?>
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
                    die("list query failed");
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
                echo "<td class='content'>";
                echo "<div id='content-normal-$pid'>$content</div>";
                echo "<div id='content-edit-$pid' style='display: none;'>";
                echo "<textarea id='content-edit-$pid-text' style='width: 100%;' rows='5'>$content</textarea>";
                echo "</div>";
                echo "</td>";

                // Problem action buttons
                // FIXME: XSS here, too
                echo "<td class='actions' style='width: 12.5%;'>";
                echo "<div id='actions-normal-$pid'>";
                echo "<button onclick='act_up($pid)'><span class='glyphicon glyphicon-chevron-up'></span></button>";
                echo "<button onclick='act_down($pid)'><span class='glyphicon glyphicon-chevron-down'></span></button>";
                echo "<br />";
                echo "<button onclick='act_edit($pid)'><span class='glyphicon glyphicon-pencil'></span></button>";
                echo "<button onclick='act_trash($pid)'><span class='glyphicon glyphicon-trash'></span></button>";
                echo "</div>";
                echo "<div id='actions-edit-$pid' style='display: none;'>";
                echo "<button onclick='edit_accept($pid);'><span class='glyphicon glyphicon-ok'></span></button>";
                echo "<button onclick='edit_reject($pid);'><span class='glyphicon glyphicon-remove'></span></button>";
                echo "</div>";
                echo "</td>";

                // End table row
                echo "</tr>";
            }
            ?>
        </table>
    </div>
</div>
<form id="action-form" method="post" style="display: none;">
    <input type="hidden" id="action-form-act" name="act"/>
    <input type="hidden" id="action-form-pid" name="pid"/>
    <input type="hidden" id="action-form-content" name="content"/>
</form>
</body>
</html>
<?php
// Close the database connection
$sql_conn->close();
?>
