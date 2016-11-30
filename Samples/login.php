<?php

require __DIR__ . '/../Lib/OwonCPI.php';
/*
 * Se necesita estar en el wifi del equipo X3 [Owon_X3_XXXXXX]
 */
$OWON = new OwonCPI();

$OWON->Login();