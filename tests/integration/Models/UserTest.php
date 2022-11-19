<?php

declare(strict_types=1);

namespace Devly\WP\Models\Tests;

use Devly\Exceptions\ObjectNotFoundException;
use Devly\WP\Models\Filter;
use Devly\WP\Models\User;
use InvalidArgumentException;
use Throwable;
use WP_UnitTestCase;
use WP_User;

use function add_filter;
use function count;
use function in_array;
use function preg_match;

class UserTest extends WP_UnitTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        update_user_meta(1, 'first_name', 'John');
        update_user_meta(1, 'last_name', 'Doe');

        $this->user = new User(1);
    }

    public function testRetrieveAllUsers(): void
    {
        $users = User::all(false);

        $this->assertInstanceOf(WP_User::class, $users[0]);

        $users = User::all();

        $this->assertInstanceOf(User::class, $users[0]);
    }

    public function testCreateUserObjectThrowsNotFoundErrorException(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        new User(10);
    }

    public function testCreateUserObjectThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User(0);
    }

    public function testFindByEmail(): void
    {
        $user = User::findByEmail('admin@example.org');

        $this->assertEquals(1, $user->ID);
    }

    public function testFindByEmailThrowsNotFoundErrorException(): void
    {
        $this->expectException(ObjectNotFoundException::class);

        User::findByEmail('fake@example.org');
    }

    public function testGetCurrentUserThrowsExceptionIfNoUserLoggedIn(): void
    {
        $this->expectException(Throwable::class);

        User::getCurrent();
    }

    public function testGetDisplayName(): void
    {
        $expected = 'admin';

        $this->assertEquals($expected, $this->user->display_name);
        $this->assertEquals($expected, $this->user->getDisplayName());
    }

    public function testGetUsername(): void
    {
        $expected = 'admin';

        $this->assertEquals($expected, $this->user->username);
        $this->assertEquals($expected, $this->user->getUsername());
    }

    public function testGetNicename(): void
    {
        $expected = 'admin';

        $this->assertEquals($expected, $this->user->nicename);
        $this->assertEquals($expected, $this->user->getNicename());
    }

    public function testGetEmail(): void
    {
        $expected = 'admin@example.org';

        $this->assertEquals($expected, $this->user->email);
        $this->assertEquals($expected, $this->user->getEmail());
    }

    public function testGetFirstName(): void
    {
        $expected = 'John';

        $this->assertEquals($expected, $this->user->first_name);
        $this->assertEquals($expected, $this->user->getFirstName());
    }

    public function testGetLastName(): void
    {
        $expected = 'Doe';

        $this->assertEquals($expected, $this->user->last_name);
        $this->assertEquals($expected, $this->user->getLastName());
    }

    public function testGetArchiveUrl(): void
    {
        $expected = 'http://example.org/?author=1';

        $this->assertEquals($expected, $this->user->getArchiveUrl());
        $this->assertEquals($expected, $this->user->archive_url);
    }

    public function testGetPassword(): void
    {
        $password = $this->user->password;

        $this->assertTrue($password === $this->user->getPassword());

        $match = preg_match('/^[a-zA-Z0-9$.\/]{34}$/', $password);

        $this->assertTrue($match !== false);
    }

    public function testGetPostCount(): void
    {
        $count = $this->user->post_count;

        $this->assertTrue($count === $this->user->getPostCount());
        $this->assertIsInt($count);
    }

    public function testSetAndGetField(): void
    {
        $this->assertTrue($this->user->setField('foo', 'bar'));
        $this->assertEquals('bar', $this->user->getField('foo'));
    }

    public function testFilterPreGetField(): void
    {
        add_filter(Filter::USER_PRE_GET_META_FIELD, static function ($value, $name) {
            if ($name !== 'bar') {
                return $value;
            }

            return 'filtered value';
        }, 0, 2);

        $this->assertEquals('filtered value', $this->user->getField('bar'));
    }

    public function testInsertUser(): void
    {
        $user = User::insert(['user_login' => 'super_user', 'user_pass' => '1234']);

        $this->assertInstanceOf(User::class, $user);
    }

    public function testInsertUserThrowsException(): void
    {
        $this->expectException(Throwable::class);

        User::insert(['user_login' => 'admin', 'user_pass' => '1234']);
    }

    public function testSetUserRole(): void
    {
        $this->user->setRole('editor');

        $roles = $this->user->roles;
        $this->assertTrue(in_array('editor', $roles));
        $this->assertTrue(count($roles) === 1);
    }

    public function testAddAndRemoveUserRole(): void
    {
        $this->user->addRole('subscriber');

        $roles = $this->user->roles;

        $this->assertTrue(in_array('subscriber', $roles));
        $this->assertTrue(count($roles) === 2);

        $this->user->removeRole('subscriber');

        $this->assertFalse(in_array('subscriber', $this->user->roles));
    }

    public function testDeleteUserFromDatabase(): void
    {
        $user = User::insert(['user_login' => 'foo_user', 'user_pass' => '1234']);
        $id   = $user->ID;

        $this->assertTrue(User::exists($id));

        User::delete($user->ID);

        $this->assertFalse(User::exists($id));
    }
}
