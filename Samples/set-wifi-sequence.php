<?php

require __DIR__ . '/../OwonCPI.php';

$OWON = new OwonCPI();

$OWON->Login();

$OWON->WIFIConfigSTA('Enviaflores', 'ENV0708288K9');

$OWON->NetworkSetupMode('wlan');