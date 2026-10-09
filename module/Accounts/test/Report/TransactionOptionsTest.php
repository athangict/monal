<?php
namespace AccountsTest\Report;

use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplateMapResolver;
use PHPUnit\Framework\TestCase;

final class TransactionOptionsTest extends TestCase
{
	public function testAjaxAccountOptionsUseSharedLabelsAndPreserveIds(): void
	{
		$path = dirname(__DIR__, 3) . '\Application\view\application\ajaxresponse\\';
		$renderer = new PhpRenderer();
		$renderer->setResolver(new TemplateMapResolver([
			'head' => $path . 'gethead.phtml',
			'subhead' => $path . 'getsubhead.phtml',
		]));
		foreach (['head' => 'heads', 'subhead' => 'subheads'] as $template => $variable) {
			$model = new ViewModel([$variable => [(object)[
				'id' => 8, 'code' => 'CBA', 'name' => 'Cash &amp; Bank &lt;Accounts&gt;',
			]]]);
			$model->setTemplate($template);
			$html = $renderer->render($model);
			self::assertStringContainsString('value="8"', $html);
			self::assertStringContainsString('CBA-Cash &amp; Bank &lt;Accounts&gt;', $html);
			self::assertStringNotContainsString('&amp;amp;', $html);
			self::assertStringNotContainsString('<Accounts>', $html);
		}
	}

	public function testAllTransactionHeadOptionsUseSharedFormatter(): void
	{
		$path = dirname(__DIR__, 2) . '\view\accounts\transaction\\';
		$count = 0;
		foreach (['add', 'edit'] as $operation) {
			foreach (['transaction', 'reference', 'expense', 'debit', 'againstdebit', 'contra', 'credit'] as $type) {
				$source = file_get_contents($path . $operation . $type . '.phtml');
				preg_match_all('/<option[^\n]*\$head\[\'id\'\][^\n]*<\/option>/', $source, $options);
				self::assertNotEmpty($options[0], $operation . $type);
				foreach ($options[0] as $option) {
					self::assertStringContainsString("AccountLabel::html(\$head['name'], \$head['code'])", $option);
					$count++;
				}
			}
		}
		self::assertSame(27, $count);
	}
}
