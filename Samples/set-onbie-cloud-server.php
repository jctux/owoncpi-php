<?php

require __DIR__ . '/../OwonCPI.php';

$OWON = new OwonCPI('10.1.12.165');

$OWON->Login();

$OWON->NetworkSetupDomainName('owon.arga-tracking.com', '54.191.8.76', '5080', '5180');

$OWON->NetworkRegisterDevice('enmaca', 'enmaca01');
