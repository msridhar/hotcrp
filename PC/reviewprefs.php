<?
require_once('../Code/confHeader.inc');
require_once('../Code/ClassPaperList.inc');
$Conf->connect();
$Me = $_SESSION["Me"];
$Me->goIfInvalid();
$Me->goIfNotPC('../index.php');

$Conf->header("Review Preferences", "revpref");

$reviewer = cvtint($_REQUEST["reviewer"]);


function savePreferences($reviewer) {
    global $Conf, $Me, $reviewTypeName;

    $setting = array();
    $error = false;
    $pmax = 0;
    foreach ($_REQUEST as $k => $v)
	if ($k[0] == 'r' && substr($k, 0, 7) == "revpref"
	    && ($p = cvtint(substr($k, 7))) > 0) {
	    if (($v = cvtint($v)) >= -1000000 && $v <= 1000000) {
		$setting[$p] = $v;
		$pmax = max($pmax, $p);
	    } else
		$error = true;
	}

    if ($error)
	$Conf->errorMsg("Reviewer preferences must be integers between -1000000 and 1000000.");
    if ($pmax == 0 && !$error)
	$Conf->errorMsg("No reviewer preferences to update.");
    if ($pmax == 0)
	return;

    $while = "while saving review preferences";
    $result = $Conf->qe("lock tables PaperReviewPreference write", $while);
    if (DB::isError($result))
	return $result;

    $delete = "delete from PaperReviewPreference where contactId=$reviewer and (";
    $orjoin = "";
    for ($p = 1; $p <= $pmax; $p++)
	if (isset($setting[$p])) {
	    $delete .= $orjoin;
	    if (!isset($setting[$p + 1]))
		$delete .= "paperId=$p";
	    else {
		$delete .= "paperId between $p and ";
		for ($p++; isset($setting[$p + 1]); $p++)
		    /* nada */;
		$delete .= $p;
	    }
	    $orjoin = " or ";
	}
    $Conf->qe($delete . ")", $while);

    for ($p = 1; $p <= $pmax; $p++)
	if (isset($setting[$p]))
	    $Conf->qe("insert into PaperReviewPreference set paperId=$p, contactId=$reviewer, preference=$setting[$p]", $while);

    $Conf->qe("unlock tables", $while);
}


if (isset($_REQUEST["update"]))
    savePreferences($Me->contactId);

echo "<p>Select a program committee member and assign that person papers to review.
Primary reviewers must review the paper themselves; secondary reviewers 
may delegate the paper or review it themselves.</p>

<p>The paper list shows all submitted papers and their topics and reviewers.
The selected PC member has high interest in topics marked with (+), and low
interest in topics marked with (&minus;).
\"Topic score\" is higher the more the PC member is interested in the paper's topics.
In the reviewer list, <sub><b>1</b></sub> indicates a primary reviewer,
and <sub><b>2</b></sub> a secondary reviewer.
Click on a column heading to sort by that column.</p>\n\n";

    
$paperList = new PaperList($_REQUEST["sort"], "reviewprefs.php?sort=");
echo "<form class='assignpc' method='post' action=\"reviewprefs.php?post=1\" enctype='multipart/form-data'>\n";
echo $paperList->text("editReviewPreference", $_SESSION['Me'], $reviewer);
echo "<input class='button_default' type='submit' name='update' value='Save preferences' />\n";
echo "</form>\n";

$Conf->footer() ?>

