<?php

// this is a custom-made file to limit permission groups. This file is the single source of truth. 
// Super Admins might be able it create new permissions, but admins can only assign them to roles and create new roles. Nothing more.

return [
	'role' => ['index', 'show', 'create', 'update', 'delete', 'core_roles', 'admin_assignment'],
	'admin' => ['index', 'show', 'create', 'update', 'delete', 'email', 'password'],
	'general' => ['index', 'show', 'create', 'update', 'delete'],

	'project' => ['index', 'show', 'create', 'update', 'delete'],
	'bar' => ['index', 'show', 'create', 'update', 'delete'],
	'deals' => ['index', 'show', 'create', 'update', 'delete'],
	'event' => ['index', 'show', 'create', 'update', 'delete'],
	'user' => ['index', 'show', 'create', 'update', 'delete'],

	'subscription' => ['index', 'show', 'create', 'delete', 'cancel'],
	'payment' => ['index', 'show', 'create', 'delete', 'cancel'],

	'points' => ['index', 'show', 'add', 'deduct', 'cancel', 'delete'],

	'course' => ['index', 'show', 'create', 'update', 'delete'],

];