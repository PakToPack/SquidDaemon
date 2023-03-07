<?php
$text_ports = "http_port %s name=port%s\nacl port%s_acl myportname port%s\n\n";

for ($i = 0; $i < $ports; $i++) echo sprintf($text_ports, ($port + $i), ($port + $i), ($port + $i), ($port + $i));

// ACL timesets
$text_acl_time = "acl ttl_%s time %s:%s-%s:%s\n";

$ttl_15_key = [];

$st_hour = 0;
$st_min = 0;

$f_hour = 0;
$f_min = 0;

$divider = 60 / $ttl;
$k = 0;

foreach ($ttl_arr as $ta) {
	$st_hour = 0;
	$st_min = 0;

	$f_hour = 0;
	$f_min = $ta - 1;

	$divider_ta = 60 / $ta;

	$day_ttl = $day_mins / $ta;

	for ($i = 0; $i < $day_ttl; $i++) {
		if ($f_hour == 24)
			$f_hour = 0;

		$st_hour_format = str_pad($st_hour, 2, 0, STR_PAD_LEFT);
		$st_min_format = str_pad($st_min, 2, 0, STR_PAD_LEFT);

		$f_hour_format = str_pad($f_hour, 2, 0, STR_PAD_LEFT);
		$f_min_format = str_pad($f_min, 2, 0, STR_PAD_LEFT);

		$ttl_15_key[$i] = sprintf('%s_%s', $ta, ($k + 1));
		echo sprintf($text_acl_time, $ttl_15_key[$i], $st_hour_format, $st_min_format, $f_hour_format, $f_min_format);

		if ($st_min + $ta < 60)
			$st_min += $ta;
		else {
			$st_min = 0;
			$st_hour += 1;
		}

		if ($f_min + $ta < 60)
			$f_min = $f_min + $ta;
		else {
			$f_hour += 1;
			$f_min = $ta - 1;
		}

		if ($k + 1 > ($divider_ta - 1))
			$k = 0;
		else
			$k += 1;
	}
}
// End of ACL time sets
?>