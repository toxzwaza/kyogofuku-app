// 名簿：ラベル発行のテンプレート（ダミー）の追加・編集・削除と、サイドバーの「名簿」。
// 実行：npm run test:js（node の標準の試験。追加の依存なし）
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { dummyAdapter as api } from '../../resources/js/services/meibo/dummyAdapter.js';

const names = async () => (await api.templates()).map((t) => t.name);

test('はじめは福井発送・岡山発送の2つ', async () => {
    assert.deepEqual(await names(), ['福井発送', '岡山発送']);
});

test('追加：新しい名前なら追加され、余計な項目は保存しない', async () => {
    const t = await api.createTemplate({ name: '  倉敷発送 ', sender_zip: '710-0000', with_family: 0, extra: 'x' });
    assert.equal(t.name, '倉敷発送');
    assert.equal(t.sender_zip, '710-0000');
    assert.equal(t.with_family, false);
    assert.equal('extra' in t, false);
    assert.deepEqual(await names(), ['福井発送', '岡山発送', '倉敷発送']);
});

test('追加：同じ名前は name_taken で断る（上書きは画面で確認してから）', async () => {
    await assert.rejects(api.createTemplate({ name: '福井発送' }), { code: 'name_taken' });
    await assert.rejects(api.createTemplate({ name: '   ' }), /テンプレート名を入れてください/);
    assert.equal((await names()).length, 3);
});

test('編集：中身と名前を書き換えられる。ほかと同じ名前にはできない', async () => {
    const [, okayama] = await api.templates();
    const saved = await api.updateTemplate(okayama.id, { name: '岡山発送（本店）', sender_address: '岡山県岡山市北区架空町1-2-3' });
    assert.equal(saved.id, okayama.id);
    assert.equal(saved.name, '岡山発送（本店）');
    assert.equal(saved.sender_address, '岡山県岡山市北区架空町1-2-3');
    await assert.rejects(api.updateTemplate(okayama.id, { name: '福井発送' }), { code: 'name_taken' });
    // 自分と同じ名前のままなら保存できる
    await api.updateTemplate(okayama.id, { name: '岡山発送（本店）', tantou: '村上' });
    await assert.rejects(api.updateTemplate(9999, { name: 'x' }), /見つかりません/);
});

test('削除：消したテンプレートは一覧から消え、同じ名前で作り直せる', async () => {
    const kurashiki = (await api.templates()).find((t) => t.name === '倉敷発送');
    await api.deleteTemplate(kurashiki.id);
    assert.deepEqual(await names(), ['福井発送', '岡山発送（本店）']);
    const again = await api.createTemplate({ name: '倉敷発送' });
    assert.notEqual(again.id, kurashiki.id);
});

test('サイドバーの「名簿」は名簿一覧だけで、名簿の3画面すべてで選択中になる', async () => {
    const src = await readFile(new URL('../../resources/js/composables/useAdminNav.js', import.meta.url), 'utf8');
    const group = src.slice(src.indexOf("group: '名簿'"));
    const items = group.slice(group.indexOf('items: ['), group.indexOf('],', group.indexOf('items: [')));
    assert.match(items, /label: '名簿一覧'.*activePatterns: \['admin\.meibo\.\*'\]/);
    assert.doesNotMatch(items, /admin\.meibo\.labels|admin\.meibo\.dashboard/);
});
