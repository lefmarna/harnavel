<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Providers\DomainServiceProvider;
use App\Providers\RepositoryServiceProvider;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    /**
     * DomainServiceProviderが正しくシングルトンを登録するかテスト
     */
    public function test_domain_service_provider_registers_bindings(): void
    {
        // テスト用の派生クラスを作成してメソッドをオーバーライド
        $provider = new class($this->app) extends DomainServiceProvider {
            /**
             * @return array<string>
             */
            protected function getPrefixes(): array
            {
                return ['TestDomain'];
            }
        };

        $provider->register();

        $contract = "App\Services\TestDomain\TestDomainServiceContract";
        
        $this->assertTrue($this->app->bound($contract), "Contract {$contract} should be bound.");
        
        // バインディングの中身が正しいか確認（クロージャか文字列か）
        // singletonの場合、makeされるまではインスタンス化されないが、
        // getBindings()で登録内容を確認できる
        $bindings = $this->app->getBindings();
        $this->assertArrayHasKey($contract, $bindings);
        
        $binding = $bindings[$contract];
        // concreteが正しく指定されているか。
        // singleton登録の場合、concreteにはクロージャが入ることが多いが、
        // 文字列指定の場合はラップされている可能性がある。
        // ここでは単純にboundされていることと、isShared（シングルトン）であることを確認する。
        
        $this->assertTrue($binding['shared'], "Binding for {$contract} should be shared (singleton).");
    }

    /**
     * RepositoryServiceProviderが正しくシングルトンを登録するかテスト
     */
    public function test_repository_service_provider_registers_bindings(): void
    {
        // テスト用の派生クラスを作成してメソッドをオーバーライド
        $provider = new class($this->app) extends RepositoryServiceProvider {
            /**
             * @return array<string>
             */
            protected function getModels(): array
            {
                return ['TestModel'];
            }
        };

        $provider->register();

        $contract = "App\Repositories\TestModel\TestModelRepositoryContract";
        
        $this->assertTrue($this->app->bound($contract), "Contract {$contract} should be bound.");
        
        $bindings = $this->app->getBindings();
        $this->assertArrayHasKey($contract, $bindings);
        
        $binding = $bindings[$contract];
        $this->assertTrue($binding['shared'], "Binding for {$contract} should be shared (singleton).");
    }
}