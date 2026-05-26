<?php

// Custom-made file to limit permission groups. Single source of truth for permissions.
// Super Admins can create new permissions; Admins can only assign and create new roles.

return [

	'role' => [
		['permission_name' => 'index', 'note' => "To view the roles page."],
		['permission_name' => 'show', 'note' => "To view a single role page."],
		['permission_name' => 'create', 'note' => "To create new roles."],
		['permission_name' => 'update', 'note' => "To update roles"],
		['permission_name' => 'delete', 'note' => "To delete roles"],
		['permission_name' => 'core_roles', 'note' => "⚠️Sensitive! Can modify or delete core roles. System might break in some cases. Assign only to super_admins."],
		['permission_name' => 'admin_assignment', 'note' => "⚠️Sensitive! Can assign admin roles to users."],
		['permission_name' => 'super_admin_assignment', 'note' => "⚠️Sensitive! Can assign super_admin roles to users."],
	],

	'admin' => [
		['permission_name' => 'index', 'note' => null],
		['permission_name' => 'show', 'note' => null],
		['permission_name' => 'create', 'note' => "⚠️Sensitive! Should not be assigned to anyone but super admins. This marks the users having superAccess."],
		['permission_name' => 'update', 'note' => "⚠️Sensitive! Should not be assigned to anyone but super admins. This marks the users having superAccess."],
		['permission_name' => 'delete', 'note' => "Can delete users with admin roles."],
		['permission_name' => 'email', 'note' => "Add this to admin, otherwise they can't even change their own email."],
		['permission_name' => 'password', 'note' => "⚠️Sensitive! Should not be assigned to anyone but super admins."],
	],

	'general' => [
		['permission_name' => 'index', 'note' => "To view general list."],
		['permission_name' => 'show', 'note' => "To view general details."],
		['permission_name' => 'create', 'note' => "To create new general item."],
		['permission_name' => 'update', 'note' => "To update general item."],
		['permission_name' => 'delete', 'note' => "To delete general item."],
	],

	'project' => [
		['permission_name' => 'index', 'note' => "To view project list."],
		['permission_name' => 'show', 'note' => "To view project details."],
		['permission_name' => 'create', 'note' => "To create new project."],
		['permission_name' => 'update', 'note' => "To update project."],
		['permission_name' => 'delete', 'note' => "To delete project."],
	],

	'user' => [
		['permission_name' => 'index', 'note' => "To view user list."],
		['permission_name' => 'show', 'note' => "To view user details."],
		['permission_name' => 'create', 'note' => "To create new user."],
		['permission_name' => 'update', 'note' => "To update user."],
		['permission_name' => 'delete', 'note' => "To delete user."],
		['permission_name' => 'admin_view', 'note' => "To view admins."],
	],

	'profile' => [
		['permission_name' => 'index', 'note' => "To view own user data, including sensitive ones."],
		['permission_name' => 'show', 'note' => "To view own user details."],
		['permission_name' => 'create', 'note' => "Generally non-functional! But can be useful to prevent certain user groups from adding exclusive fields like phone/bio to their profiles."],
		['permission_name' => 'update', 'note' => "To update own user data."],
		['permission_name' => 'delete', 'note' => "✅To delete own account. Must be checked for all to pass App Store verification."],
	],
	
	'bar' => [
		['permission_name' => 'index', 'note' => "To view bar list."],
		['permission_name' => 'show', 'note' => "To view bar details."],
		['permission_name' => 'create', 'note' => "To create new bar."],
		['permission_name' => 'update', 'note' => "To update bar."],
		['permission_name' => 'delete', 'note' => "To delete bar."],
	],

	'deals' => [
		['permission_name' => 'index', 'note' => "To view deals list."],
		['permission_name' => 'show', 'note' => "To view deal details."],
		['permission_name' => 'create', 'note' => "To create new deal."],
		['permission_name' => 'update', 'note' => "To update deal."],
		['permission_name' => 'delete', 'note' => "To delete deal."],
	],

	'event' => [
		['permission_name' => 'index', 'note' => "To view event list."],
		['permission_name' => 'show', 'note' => "To view event details."],
		['permission_name' => 'create', 'note' => "To create new event."],
		['permission_name' => 'update', 'note' => "To update event."],
		['permission_name' => 'delete', 'note' => "To delete event."],
	],

	'subscription' => [
		['permission_name' => 'index', 'note' => "To view subscription list."],
		['permission_name' => 'show', 'note' => "To view subscription details."],
		['permission_name' => 'create', 'note' => "To create new subscription."],
		['permission_name' => 'update', 'note' => "To update old generic/stale subscription data. Doesn't update transactions."],
		['permission_name' => 'delete', 'note' => "To delete subscription."],
		['permission_name' => 'cancel', 'note' => "To cancel subscription."],
	],

	'payment' => [
		['permission_name' => 'index', 'note' => "To view payment list."],
		['permission_name' => 'show', 'note' => "To view payment details."],
		['permission_name' => 'create', 'note' => "To create new payment."],
		['permission_name' => 'update', 'note' => "To update old generic/stale payment data. Doesn't update transactions."],
		['permission_name' => 'delete', 'note' => "To delete payment."],
		['permission_name' => 'cancel', 'note' => "To cancel payment."],
	],

	'points' => [
		['permission_name' => 'index', 'note' => "To view points."],
		['permission_name' => 'show', 'note' => "To view points details."],
		['permission_name' => 'add', 'note' => "To add points."],
		['permission_name' => 'deduct', 'note' => "To deduct points."],
		['permission_name' => 'cancel', 'note' => "To cancel points transaction."],
		['permission_name' => 'delete', 'note' => "To delete points record."],
	],

	'course' => [
		['permission_name' => 'index', 'note' => "To view course list."],
		['permission_name' => 'show', 'note' => "To view course details."],
		['permission_name' => 'create', 'note' => "To create new course."],
		['permission_name' => 'update', 'note' => "To update course."],
		['permission_name' => 'delete', 'note' => "To delete course."],
	],

];