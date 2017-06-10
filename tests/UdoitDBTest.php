<?php
/**
*   Copyright (C) 2014 University of Central Florida, created by Jacob Bates, Eric Colon, Fenel Joseph, and Emily Sachs.
*
*   This program is free software: you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation, either version 3 of the License, or
*   (at your option) any later version.
*
*   This program is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*   Primary Author Contact:  Jacob Bates <jacob.bates@ucf.edu>
*/

class PDOStatementMock extends \PDOStatement {

    public $bind_value_calls = [];
    public $execute_calls = [];

    public function bindValue($paramno, $param, $type = NULL){
        $this->bind_value_calls[] = func_get_args();
    }

    public function execute($bound_input_params = NULL){
        $this->execute_calls[] = func_get_args();
        return true;
    }

    public function fetchColumn($column_number = NULL){
        return $this->mockFetch();
    }

    public function fetchObject($class_name = NULL, $ctor_args = NULL){
        return $this->mockFetch();
    }

    protected function mockFetch(){
        $val = PDOMock::$next_fetch_return;
        PDOMock::$next_fetch_return = null;
        return $val;
    }

    public function next() {
        return $this->mockFetch();
    }
}

class PDOMock extends \PDO {
    public $constructor_args = [];
    public $query_calls = [];
    public $prepare_calls = [];
    public $begin_transaction_calls = [];
    public $commit_calls = [];
    public $statements = [];
    public $query_returns_data = false;
    public static $next_fetch_return = null;

    public function __construct() {
        $this->constructor_args = func_get_args();
    }

    public function query(){
        $this->query_calls[] = func_get_args();
        if($this->query_returns_data) return self::$next_fetch_return;
        return $this->_newStatement();
    }

    public function prepare($statement, $options = NULL){
        $this->prepare_calls[] = func_get_args();
        return $this->_newStatement();
    }

    public function beginTransaction(){
        $this->begin_transaction_calls[] = func_get_args();
    }

    public function commit(){
        $this->commit_calls[] = func_get_args();
    }

    public function nextFetchReturns($value){
        self::$next_fetch_return = $value;
    }

    protected function _newStatement(){
        $stmt = new PDOStatementMock();
        $this->statements[] = $stmt;
        return $stmt;
    }
}

class UdoitDBTest extends BaseTest{

    protected function setUp() {
        self::setPrivateStaticPropertyValue('UdoitDB', 'dbClass', 'PDOMock');
    }

    protected function tearDown(){
        self::clearMockDBConnection();
    }

    public function testMysqlSetup(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');

        self::assertEquals('mysql', self::getPrivateStaticPropertyValue('UdoitDB', 'type'));
        self::assertEquals('b', self::getPrivateStaticPropertyValue('UdoitDB', 'dsn'));
        self::assertEquals('c', self::getPrivateStaticPropertyValue('UdoitDB', 'user'));
        self::assertEquals('d', self::getPrivateStaticPropertyValue('UdoitDB', 'password'));
    }

    public function testPsqlSetup(){
        UdoitDB::setup('pgsql', 'b', 'c', 'd');

        self::assertEquals('pgsql', self::getPrivateStaticPropertyValue('UdoitDB', 'type'));
        self::assertEquals('b', self::getPrivateStaticPropertyValue('UdoitDB', 'dsn'));
        self::assertEquals('c', self::getPrivateStaticPropertyValue('UdoitDB', 'user'));
        self::assertEquals('d', self::getPrivateStaticPropertyValue('UdoitDB', 'password'));
    }

    public function testConnectMysql(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        UdoitDB::testAndReconnect();
        $pdo = self::getPrivateStaticPropertyValue('UdoitDB', 'pdo');
        self::assertInstanceOf(PDOMock, $pdo);
        self::assertArraySubset(['b', 'c', 'd'], $pdo->constructor_args);
    }

    public function testConnectPgsql(){
        UdoitDB::setup('pgsql', 'b', 'c', 'd');
        UdoitDB::testAndReconnect();
        $pdo = self::getPrivateStaticPropertyValue('UdoitDB', 'pdo');
        self::assertInstanceOf(PDOMock, $pdo);
        self::assertArraySubset(['b'], $pdo->constructor_args);
    }

    public function testDisconnect(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        UdoitDB::testAndReconnect();
        self::assertInstanceOf(PDOMock, self::getPrivateStaticPropertyValue('UdoitDB', 'pdo'));
        UdoitDB::disconnect();
        self::assertNull(self::getPrivateStaticPropertyValue('UdoitDB', 'pdo'));
    }

    public function testConnectionTestWithoutConnection(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        self::assertFalse(UdoitDB::test());
    }

    public function testConnectionTestWithConnectionBeforeTimeoutDoesntRunQuery(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        self::callProtectedStaticMethod('UdoitDB', 'connect');

        self::assertTrue(UdoitDB::test());
        $pdo = self::getPrivateStaticPropertyValue('UdoitDB', 'pdo');
        self::assertEmpty($pdo->query_calls);
    }

    public function testConnectionTestWithConnectionBeforeTimeoutWithForceOnDoesRunQuery(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        self::callProtectedStaticMethod('UdoitDB', 'connect');

        self::assertTrue(UdoitDB::test(true));
        $pdo = self::getPrivateStaticPropertyValue('UdoitDB', 'pdo');
        self::assertCount(1, $pdo->query_calls);
    }

    public function testConnectionTestWithConnectionAfterTimeoutDoesRunQuery(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        self::callProtectedStaticMethod('UdoitDB', 'connect');
        self::setPrivateStaticPropertyValue('UdoitDB', 'last_test_time', 0);

        self::assertTrue(UdoitDB::test());
        $pdo = self::getPrivateStaticPropertyValue('UdoitDB', 'pdo');
        self::assertCount(1, $pdo->query_calls);
    }

    public function testPDOPassThroughCallsPDOFunction(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        self::callProtectedStaticMethod('UdoitDB', 'connect');

        UdoitDB::query('QUERY VALUE HERE');
        $pdo = self::getPrivateStaticPropertyValue('UdoitDB', 'pdo');
        self::assertEquals('QUERY VALUE HERE', $pdo->query_calls[0][0]);
    }

    public function testPDOPassThroughWillReconnect(){
        UdoitDB::setup('mysql', 'b', 'c', 'd');
        self::assertFalse(UdoitDB::test());

        UdoitDB::query('QUERY VALUE HERE');
        self::assertTrue(UdoitDB::test());
    }

}
