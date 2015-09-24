<?php

namespace Tests\Connector\Sabre;

/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
class FilesPlugin extends \Test\TestCase {
	const GETETAG_PROPERTYNAME = \OCA\DAV\Connector\Sabre\FilesPlugin::GETETAG_PROPERTYNAME;
	const FILEID_PROPERTYNAME = \OCA\DAV\Connector\Sabre\FilesPlugin::FILEID_PROPERTYNAME;
	const SIZE_PROPERTYNAME = \OCA\DAV\Connector\Sabre\FilesPlugin::SIZE_PROPERTYNAME;
	const PERMISSIONS_PROPERTYNAME = \OCA\DAV\Connector\Sabre\FilesPlugin::PERMISSIONS_PROPERTYNAME;
	const GETLASTMODIFIED_PROPERTYNAME = \OCA\DAV\Connector\Sabre\FilesPlugin::GETLASTMODIFIED_PROPERTYNAME;
	const DOWNLOADURL_PROPERTYNAME = \OCA\DAV\Connector\Sabre\FilesPlugin::DOWNLOADURL_PROPERTYNAME;

	/**
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var \Sabre\DAV\ObjectTree
	 */
	private $tree;

	/**
	 * @var \OCA\DAV\Connector\Sabre\FilesPlugin
	 */
	private $plugin;

	public function setUp() {
		parent::setUp();
		$this->server = new \Sabre\DAV\Server();
		$this->tree = $this->getMockBuilder('\Sabre\DAV\Tree')
			->disableOriginalConstructor()
			->getMock();
		$this->plugin = new \OCA\DAV\Connector\Sabre\FilesPlugin($this->tree);
		$this->plugin->initialize($this->server);
	}

	private function createTestNode($class) {
		$node = $this->getMockBuilder($class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())
			->method('getId')
			->will($this->returnValue(123));

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/dummypath')
			->will($this->returnValue($node));

		$node->expects($this->any())
			->method('getFileId')
			->will($this->returnValue(123));
		$node->expects($this->any())
			->method('getEtag')
			->will($this->returnValue('"abc"'));
		$node->expects($this->any())
			->method('getDavPermissions')
			->will($this->returnValue('DWCKMSR'));

		return $node;
	}

	/**
	 */
	public function testGetPropertiesForFile() {
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\File');

		$propFind = new \Sabre\DAV\PropFind(
			'/dummyPath',
			array(
				self::GETETAG_PROPERTYNAME,
				self::FILEID_PROPERTYNAME,
				self::SIZE_PROPERTYNAME,
				self::PERMISSIONS_PROPERTYNAME,
				self::DOWNLOADURL_PROPERTYNAME,
			),
			0
		);

		$node->expects($this->once())
			->method('getDirectDownload')
			->will($this->returnValue(array('url' => 'http://example.com/')));
		$node->expects($this->never())
			->method('getSize');

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals('"abc"', $propFind->get(self::GETETAG_PROPERTYNAME));
		$this->assertEquals(123, $propFind->get(self::FILEID_PROPERTYNAME));
		$this->assertEquals(null, $propFind->get(self::SIZE_PROPERTYNAME));
		$this->assertEquals('DWCKMSR', $propFind->get(self::PERMISSIONS_PROPERTYNAME));
		$this->assertEquals('http://example.com/', $propFind->get(self::DOWNLOADURL_PROPERTYNAME));
		$this->assertEquals(array(self::SIZE_PROPERTYNAME), $propFind->get404Properties());
	}

	public function testGetPublicPermissions() {
		$this->plugin = new \OCA\DAV\Connector\Sabre\FilesPlugin($this->tree, true);
		$this->plugin->initialize($this->server);

		$propFind = new \Sabre\DAV\PropFind(
			'/dummyPath',
			[
				self::PERMISSIONS_PROPERTYNAME,
			],
			0
		);

		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\File');
		$node->expects($this->any())
			->method('getDavPermissions')
			->will($this->returnValue('DWCKMSR'));

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals('DWCKR', $propFind->get(self::PERMISSIONS_PROPERTYNAME));
	}

	public function testGetPropertiesForDirectory() {
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\Directory');

		$propFind = new \Sabre\DAV\PropFind(
			'/dummyPath',
			array(
				self::GETETAG_PROPERTYNAME,
				self::FILEID_PROPERTYNAME,
				self::SIZE_PROPERTYNAME,
				self::PERMISSIONS_PROPERTYNAME,
				self::DOWNLOADURL_PROPERTYNAME,
			),
			0
		);

		$node->expects($this->never())
			->method('getDirectDownload');
		$node->expects($this->once())
			->method('getSize')
			->will($this->returnValue(1025));

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals('"abc"', $propFind->get(self::GETETAG_PROPERTYNAME));
		$this->assertEquals(123, $propFind->get(self::FILEID_PROPERTYNAME));
		$this->assertEquals(1025, $propFind->get(self::SIZE_PROPERTYNAME));
		$this->assertEquals('DWCKMSR', $propFind->get(self::PERMISSIONS_PROPERTYNAME));
		$this->assertEquals(null, $propFind->get(self::DOWNLOADURL_PROPERTYNAME));
		$this->assertEquals(array(self::DOWNLOADURL_PROPERTYNAME), $propFind->get404Properties());
	}

	public function testUpdateProps() {
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\File');

		$testDate = 'Fri, 13 Feb 2015 00:01:02 GMT';

		$node->expects($this->once())
			->method('touch')
			->with($testDate);

		$node->expects($this->once())
			->method('setEtag')
			->with('newetag')
			->will($this->returnValue(true));

		// properties to set
		$propPatch = new \Sabre\DAV\PropPatch(array(
			self::GETETAG_PROPERTYNAME => 'newetag',
			self::GETLASTMODIFIED_PROPERTYNAME => $testDate
		));

		$this->plugin->handleUpdateProperties(
			'/dummypath',
			$propPatch
		);

		$propPatch->commit();

		$this->assertEmpty($propPatch->getRemainingMutations());

		$result = $propPatch->getResult();
		$this->assertEquals(200, $result[self::GETLASTMODIFIED_PROPERTYNAME]);
		$this->assertEquals(200, $result[self::GETETAG_PROPERTYNAME]);
	}

}
