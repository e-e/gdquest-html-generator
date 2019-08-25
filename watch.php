<?php

$scriptPath = "./generate-html.php";
$script = file_get_contents($scriptPath);

while (1) {
    $newScript = file_get_contents($scriptPath);

    if ($script !== $newScript) {
        exec("php $scriptPath");
        echo "Script change detected, output regenerated.\n";
    }

    $script = $newScript;
    sleep(1);
}