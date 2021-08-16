<?php

require_once("../regapi/llsd.php");
$config = array(
    #1 is mainland
    "estate" => 1,
    #Wengen is http://maps.secondlife.com/secondlife/Wengen/23/215/1101
    #This is a public Linden owned lodge
    "region" => "Wengen",
    #Put them in front of the lodge
    "location" => [23, 211, 86],
    #Make them face south
    "lookat" => [0, -1, 0],
    #If you have an experience, you can put it here:
    "experience" => new UUID("00000000-0000-0000-0000-000000000000"),
    #If you have a group, put it's group name here:
    "group" => ""
);

#Capability list, paste what you get from getCapabilities.php
$caps = array(
    "example_capability": "https://cap.secondlife.com/cap/0/UUID"
);