import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { getInitials } from './initials'

describe('イニシャル生成', function () {
  it('姓と名から両方の頭文字を大文字で返す', function () {
    assert.equal(getInitials('taro yamada'), 'TY')
  })

  it('名前が1語の場合は先頭の1文字だけを大文字で返す', function () {
    assert.equal(getInitials('taro'), 'T')
  })

  it('3語以上の場合は最初と最後の頭文字を返す', function () {
    assert.equal(getInitials('john ronald reuel tolkien'), 'JT')
  })

  it('前後に空白がある場合は無視して頭文字を返す', function () {
    assert.equal(getInitials('  taro yamada  '), 'TY')
  })

  it('語の間に空白が連続する場合も最初と最後の頭文字を返す', function () {
    assert.equal(getInitials('taro   yamada'), 'TY')
  })

  it('タブや改行区切りでも最初と最後の頭文字を返す', function () {
    assert.equal(getInitials('taro\tyamada'), 'TY')
    assert.equal(getInitials('john\nronald\treuel tolkien'), 'JT')
  })

  it('空文字の場合は空文字を返す', function () {
    assert.equal(getInitials(''), '')
  })

  it('空白のみの場合は空文字を返す', function () {
    assert.equal(getInitials('   '), '')
  })
})
