<?php

namespace Tests;

/**
 * Base test case for tenancy tests that provision real PostgreSQL tenant
 * databases. These tests cannot use the RefreshDatabase transaction wrapper
 * because PostgreSQL cannot run CREATE DATABASE inside a transaction.
 */
abstract class TenancyTestCase extends TestCase
{
    //
}
