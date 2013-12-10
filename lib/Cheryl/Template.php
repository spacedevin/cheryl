<?php

if (!class_exists('Cheryl_Template')) {
	class Cheryl_Template {
		public static function build($dir, $template, $show = false) {
			if ($show) {
				ob_start();
			}

			$path = $dir.DIRECTORY_SEPARATOR.'Template'.DIRECTORY_SEPARATOR.$template;

			if (file_exists($path.'.phtml')) {
				// use the template
				$template = file_get_contents($path.'.phtml');
				if (file_exists($path.'.css')) {
					$template = str_replace('<style></style>','<style>'.file_get_contents($path.'.css').'</style>', $template);
				}
				if (file_exists($path.'.js')) {
					$template = str_replace('<script></script>','<script>'.file_get_contents($path.'.js').'</script>', $template);
				}

				if ($show) {
					$temp = tempnam(null,'cheryl-template');
					file_put_contents($temp, $template);
					include($temp);

				} else {
					$res = $template;
				}
			}

			if ($show) {
				$res = ob_get_contents();
				ob_end_clean();
			}
			return $res;
		}

		public static function show() {
			$res = self::build(Cheryl::me()->config->includes, Cheryl::me()->config->templateName, true);
			if (!$res) {
				ob_start();
				/* <TEMPLATE_CONTENT> */
				$res = ob_get_contents();
				ob_end_clean();
			}
			return $res;
		}
	}
}