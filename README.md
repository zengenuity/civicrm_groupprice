# THIS PROJECT HAS MOVED TO https://lab.civicrm.org/extensions/civicrm_groupprice

## Introduction
This CiviCRM extension allows you to limit the display of individual prices in
price sets to specific groups. This allows you to implement things like
members-only pricing for events (by using a Smart Group to track current
members). This extension works with both static and smart groups.

## Installation
1. Copy the civicrm_groupprice folder into your CiviCRM extensions directory.
2. Go to Administer > System Settings > Manage Extensions.
3. Enable the "Limit Prices by Group" extension.

## Usage
This extension adds two fields to the price option form found under
Price Sets > View and Edit Price Fields > Edit Price Options > Edit
Option. The "Limit Price Option To" field allows you select groups that will
be allowed to see this price option. If no groups are selected, everyone can
see the price option. The "Negate Price Group Limit" checkbox allows you
to negate the group selection, meaning only users who are NOT in the selected
groups can see the price option.

## Members-Only Pricing
Probably the most common thing people will want to do with this is members-only
pricing for events. To implement this you need to create a smart group for
current members. Once you have that done, you can just select the members group
in the price option form as described above.

## Credit
This extension was written by Wayne Eaker and modeled after
[qjensen's CiviCRM-Member-Pricing module](https://github.com/qjensen/CiviCRM-Member-Pricing).

It was largely rewritten by Aidan Saunders (hello@squiffle.uk)
