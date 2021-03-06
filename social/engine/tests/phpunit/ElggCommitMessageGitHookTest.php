<?php
/**
 * Tests the commit message validation shell script used by the git hook and travis
 */

class ElggCommitMessageGitHookTest extends PHPUnit_Framework_TestCase {
	protected $scriptsDir;
	protected $filesDir;
	protected $validateScript;

	protected $validTypes = array(
		'feature',
		'fix',
		'docs',
		'chore',
		'perf',
		'security'
	);

	public function setUp() {
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$this->markTestSkipped('Can only test in *nix envs.');
		}

		$this->scriptsDir = dirname(dirname(dirname(dirname(__FILE__)))) . '/.scripts/';
		$this->filesDir = dirname(__FILE__) . '/test_files/commit_messages/';
		$this->validateScript = "php {$this->scriptsDir}validate_commit_msg.php";

		parent::setUp();
	}
	
	/**
	 * Test failures for missing input
	 */
	public function testInvalidInputs() {
		// have to pass an empty arg because it looks for stdin
		$cmd = "$this->validateScript ''";
		$result = $this->runCmd($cmd, $output);
		$this->assertFalse($result, $output);

		$cmd = "$this->validateScript /dev/null";
		$result = $this->runCmd($cmd, $output);
		$this->assertFalse($result, $output);

		$cmd = "echo '' | $this->validateScript";
		$result = $this->runCmd($cmd, $output);
		$this->assertFalse($result, $output);
	}

	public function testInvalidMessage() {
		$cmd = "$this->validateScript {$this->filesDir}invalid_format.txt";
		$result = $this->runCmd($cmd, $output);
		$this->assertFalse($result, $output);
	}

	public function testFile() {
		$cmd = "$this->validateScript {$this->filesDir}valid.txt";
		$result = $this->runCmd($cmd, $output);
		$this->assertTrue($result, $output);
	}
	
	public function testPipe() {
		$msg = escapeshellarg(file_get_contents("{$this->filesDir}valid.txt"));
		$cmd = "echo $msg | $this->validateScript";
		$result = $this->runCmd($cmd, $output);
		$this->assertTrue($result, $output);
	}

	public function testArg() {
		$msg = escapeshellarg(file_get_contents("{$this->filesDir}valid.txt"));
		$cmd = "$this->validateScript $msg";
		$result = $this->runCmd($cmd, $output);
		$this->assertTrue($result, $output);
	}

	/**
	 * Executes a command and returns true if the cmd
	 * exited with 0.
	 *
	 * @param string $cmd    Shell command to execute
	 * @param string $output Output from stdout and stderr will be written to this variable
	 * @param array  $env    Array of environment variables to be passed to sub-process
	 * @return bool Result depending on process exit code.
	 */
	protected function runCmd($cmd, &$output, array $env = array()) {
		$descriptorspec = array(
			0 => array("pipe", "r"),// stdin
			1 => array("pipe", "w"),// stdout
			2 => array("pipe", "w"),// stderr
		);
		$defaultEnv = array(
			'PATH' => getenv('PATH'),// we need to copy PATH variable to run php without specifying absolute path
		);
		$env = array_merge($defaultEnv, $env);

		$process = proc_open($cmd, $descriptorspec, $pipes, null, $env);
		$this->assertTrue(is_resource($process));

		// unfortunately we separate errors from output, but it should be good enough for current usage
		$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

		$exit = proc_close($process);

		return $exit > 0 ? false : true;
	}
}