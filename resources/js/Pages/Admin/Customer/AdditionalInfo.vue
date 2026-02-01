<template>
    <Head :title="`追加情報 - ${customer.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">追加情報（振袖アンケート）</h2>
                <Link
                    :href="route('admin.customers.show', customer.id)"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50"
                >
                    顧客詳細に戻る
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <header class="px-6 py-4 border-b border-gray-200 text-center">
                        <h1 class="text-xl font-semibold text-gray-900">あなたにぴったりのお振袖をお探しします。</h1>
                        <p class="mt-1 text-sm text-gray-500">あなたのことをお教えください！</p>
                    </header>

                    <!-- 記入日 -->
                    <div class="px-6 py-3 flex flex-wrap justify-end items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500">記入日：</span>
                        <input v-model="form.entry_date_year" type="text" inputmode="numeric" placeholder="年" class="w-20 rounded-lg border-gray-300 text-sm" />
                        <span>年</span>
                        <input v-model="form.entry_date_month" type="text" inputmode="numeric" placeholder="月" class="w-14 rounded-lg border-gray-300 text-sm" />
                        <span>月</span>
                        <input v-model="form.entry_date_day" type="text" inputmode="numeric" placeholder="日" class="w-14 rounded-lg border-gray-300 text-sm" />
                        <span>日</span>
                    </div>

                    <!-- 基本情報 -->
                    <section class="px-6 py-4 border-t border-gray-200">
                        <h2 class="text-base font-bold border-l-4 border-gray-900 pl-2 mb-3">基本情報</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">お名前（お嬢様）</label>
                                <input v-model="form.name_daughter" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">ふりがな（お嬢様）</label>
                                <input v-model="form.furigana_daughter" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">お名前（お母様）</label>
                                <input v-model="form.name_mother" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">ふりがな（お母様）</label>
                                <input v-model="form.furigana_mother" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="" />
                            </div>
                        </div>

                        <div class="border-t border-gray-200 my-4"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">お誕生日（西暦）</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input v-model="form.birth_year" type="text" inputmode="numeric" placeholder="年" class="w-20 rounded-lg border-gray-300 text-sm" />
                                    <span>年</span>
                                    <input v-model="form.birth_month" type="text" inputmode="numeric" placeholder="月" class="w-14 rounded-lg border-gray-300 text-sm" />
                                    <span>月</span>
                                    <input v-model="form.birth_day" type="text" inputmode="numeric" placeholder="日" class="w-14 rounded-lg border-gray-300 text-sm" />
                                    <span>日</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">身長</label>
                                    <div class="flex items-center gap-1">
                                        <input v-model="form.height" type="text" inputmode="numeric" placeholder="例：160" class="w-20 rounded-lg border-gray-300 text-sm" />
                                        <span>cm</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">足サイズ</label>
                                    <div class="flex items-center gap-1">
                                        <input v-model="form.foot_size" type="text" inputmode="numeric" placeholder="例：24.0" class="w-20 rounded-lg border-gray-300 text-sm" />
                                        <span>cm</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 my-4"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">ご住所</label>
                                <div class="flex flex-wrap items-center gap-1 mb-2">
                                    <span>〒</span>
                                    <input v-model="form.postal_code" type="text" inputmode="numeric" placeholder="例：710-0000" class="w-28 rounded-lg border-gray-300 text-sm" />
                                </div>
                                <input v-model="form.address" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="住所を入力してください" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">お電話</label>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs font-bold text-gray-500 w-16 shrink-0">ご自宅</span>
                                        <input v-model="form.phone_home_1" type="tel" inputmode="tel" placeholder="000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                        <span>－</span>
                                        <input v-model="form.phone_home_2" type="tel" inputmode="tel" placeholder="0000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                        <span>－</span>
                                        <input v-model="form.phone_home_3" type="tel" inputmode="tel" placeholder="0000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs font-bold text-gray-500 w-16 shrink-0">お嬢様</span>
                                        <input v-model="form.phone_daughter_1" type="tel" inputmode="tel" placeholder="000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                        <span>－</span>
                                        <input v-model="form.phone_daughter_2" type="tel" inputmode="tel" placeholder="0000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                        <span>－</span>
                                        <input v-model="form.phone_daughter_3" type="tel" inputmode="tel" placeholder="0000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs font-bold text-gray-500 w-16 shrink-0">お母様</span>
                                        <input v-model="form.phone_mother_1" type="tel" inputmode="tel" placeholder="000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                        <span>－</span>
                                        <input v-model="form.phone_mother_2" type="tel" inputmode="tel" placeholder="0000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                        <span>－</span>
                                        <input v-model="form.phone_mother_3" type="tel" inputmode="tel" placeholder="0000" class="w-14 rounded-lg border-gray-300 text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- お好み -->
                    <section class="px-6 py-4 border-t border-gray-200">
                        <h2 class="text-base font-bold border-l-4 border-gray-900 pl-2 mb-3">お好み</h2>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-2">好きな色</label>
                            <div class="flex flex-wrap gap-2 items-center">
                                <label v-for="c in colorOptions" :key="c" class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                    <input v-model="form.color" type="checkbox" :value="c" class="rounded accent-gray-800" />
                                    {{ c }}
                                </label>
                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                    <input v-model="showColorOther" type="checkbox" class="rounded accent-gray-800" />
                                    その他
                                </label>
                                <input
                                    v-if="showColorOther"
                                    v-model="form.color_other"
                                    type="text"
                                    class="w-32 rounded-lg border-gray-300 text-sm"
                                    placeholder="追加する色"
                                />
                            </div>
                        </div>
                        <div class="border-t border-gray-200 my-4"></div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-2">好きな事</label>
                            <div class="flex flex-wrap gap-2 items-center">
                                <label v-for="h in hobbyOptions" :key="h" class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                    <input v-model="form.hobby" type="checkbox" :value="h" class="rounded accent-gray-800" />
                                    {{ h }}
                                </label>
                                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                    <input v-model="showHobbyOther" type="checkbox" class="rounded accent-gray-800" />
                                    その他
                                </label>
                                <input
                                    v-if="showHobbyOther"
                                    v-model="form.hobby_other"
                                    type="text"
                                    class="w-32 rounded-lg border-gray-300 text-sm"
                                    placeholder="追加する事"
                                />
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs font-bold text-gray-500 mb-1">スポーツ（自由記入）</label>
                                <input v-model="form.sports_detail" type="text" class="w-full max-w-xs rounded-lg border-gray-300 text-sm" placeholder="例：バレーボール" />
                            </div>
                        </div>
                        <div class="border-t border-gray-200 my-4"></div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">お振袖の好きな柄やイメージをお持ちですか？</label>
                            <textarea v-model="form.furisode_image" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="例：古典柄、かわいい系、シンプル…"></textarea>
                        </div>
                    </section>

                    <!-- 学生の方へ -->
                    <section class="px-6 py-4 border-t border-gray-200">
                        <h2 class="text-base font-bold border-l-4 border-gray-900 pl-2 mb-3">学生の方へ</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">卒業予定年度</label>
                                <div class="flex items-center gap-1">
                                    <input v-model="form.graduation_year" type="text" inputmode="numeric" placeholder="年" class="w-20 rounded-lg border-gray-300 text-sm" />
                                    <span>年</span>
                                    <span class="font-bold">3月</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">※原本の表記に合わせて「3月」を固定表示しています。</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">卒業式にハカマを着たいですか？</label>
                                <div class="flex flex-wrap gap-2">
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                        <input v-model="form.hakama" type="radio" value="はい" class="accent-gray-800" />
                                        はい
                                    </label>
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                        <input v-model="form.hakama" type="radio" value="いいえ" class="accent-gray-800" />
                                        いいえ
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ご希望 -->
                    <section class="px-6 py-4 border-t border-gray-200">
                        <h2 class="text-base font-bold border-l-4 border-gray-900 pl-2 mb-3">ご希望</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">購入／レンタル</label>
                                <div class="flex flex-wrap gap-2">
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                        <input v-model="form.plan" type="radio" value="購入" class="accent-gray-800" />
                                        購入
                                    </label>
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                        <input v-model="form.plan" type="radio" value="レンタル" class="accent-gray-800" />
                                        レンタル
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">ママ振り（持込み）／お振袖のフォト</label>
                                <div class="flex flex-wrap gap-2">
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                        <input v-model="form.option" type="checkbox" value="ママ振り（持込み）" class="rounded accent-gray-800" />
                                        ママ振り（持込み）
                                    </label>
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                        <input v-model="form.option" type="checkbox" value="お振袖のフォト" class="rounded accent-gray-800" />
                                        お振袖のフォト
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 my-4"></div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2">価格帯（複数選択可）</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
                                <label v-for="p in priceOptions" :key="p" class="inline-flex items-center gap-2 px-3 py-2 rounded-full border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer">
                                    <input v-model="form.price" type="checkbox" :value="p" class="rounded accent-gray-800" />
                                    {{ p }}
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- 進路・状況 -->
                    <section class="px-6 py-4 border-t border-gray-200">
                        <h2 class="text-base font-bold border-l-4 border-gray-900 pl-2 mb-3">進路・状況</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">大学生（学校名）</label>
                                <input v-model="form.university" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="（　　　　　　　　　　　　）" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">短大・専門（学校名）</label>
                                <input v-model="form.college" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="（　　　　　　　　　　　）" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">アルバイト</label>
                                <select v-model="form.parttime" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">選択してください</option>
                                    <option value="している">している</option>
                                    <option value="していない">していない</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">お勤め</label>
                                <select v-model="form.work" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">選択してください</option>
                                    <option value="している">している</option>
                                    <option value="していない">していない</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">その他</label>
                                <input v-model="form.other_status" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="（　　　　　　　　　　　　）" />
                            </div>
                        </div>
                    </section>

                    <!-- ご姉妹 -->
                    <section class="px-6 py-4 border-t border-gray-200">
                        <h2 class="text-base font-bold border-l-4 border-gray-900 pl-2 mb-3">ご姉妹がいらっしゃいますか？</h2>
                        <div class="space-y-3">
                            <div v-for="(sister, index) in form.sisters" :key="index" class="flex flex-wrap items-center gap-2">
                                <label class="text-xs font-bold text-gray-500 w-40 shrink-0">{{ index + 1 }}人目（さま／生年月日）</label>
                                <input v-model="sister.name" type="text" class="min-w-[200px] rounded-lg border-gray-300 text-sm" placeholder="例：○○ さま" />
                                <input v-model="sister.year" type="text" inputmode="numeric" placeholder="年" class="w-16 rounded-lg border-gray-300 text-sm" />
                                <span>年</span>
                                <input v-model="sister.month" type="text" inputmode="numeric" placeholder="月" class="w-12 rounded-lg border-gray-300 text-sm" />
                                <span>月</span>
                                <input v-model="sister.day" type="text" inputmode="numeric" placeholder="日" class="w-12 rounded-lg border-gray-300 text-sm" />
                                <span>日生</span>
                                <button type="button" @click="removeSister(index)" class="p-1.5 text-gray-500 hover:text-red-600 rounded" title="削除">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <button type="button" @click="addSister" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-dashed border-gray-400 text-gray-600 text-sm font-medium hover:bg-gray-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                追加
                            </button>
                        </div>
                    </section>

                    <!-- 来店動機（予約フォームと同一のチェックボックス） -->
                    <section class="px-6 py-4 border-t border-gray-200">
                        <h2 class="text-base font-bold border-l-4 border-gray-900 pl-2 mb-3">ご来店の動機</h2>
                        <div class="space-y-2">
                            <label
                                v-for="reason in visitReasonOptions"
                                :key="reason.value"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm font-bold cursor-pointer hover:bg-gray-100"
                            >
                                <input
                                    v-model="form.visit_reasons"
                                    type="checkbox"
                                    :value="reason.value"
                                    class="rounded accent-gray-800"
                                />
                                <span>{{ reason.label }}</span>
                            </label>
                            <div v-if="form.visit_reasons && form.visit_reasons.includes('その他')" class="ml-6 mt-2">
                                <input
                                    v-model="form.visit_reason_other"
                                    type="text"
                                    placeholder="その他の内容を入力してください"
                                    class="w-full rounded-lg border-gray-300 text-sm py-2 px-3"
                                />
                            </div>
                        </div>
                    </section>

                    <!-- 担当 -->
                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex flex-wrap items-center justify-between gap-4">
                        <span class="font-bold">ありがとうございました。</span>
                        <div class="flex items-center gap-2">
                            <span>担当：</span>
                            <input v-model="form.staff_name" type="text" class="w-32 rounded-lg border-gray-300 text-sm" placeholder="＿＿＿＿" />
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? '送信中...' : '確定する' }}
                        </button>
                    </div>
                </form>

                <p class="mt-3 text-right text-xs text-gray-500">※このデジタル文書は「振袖アンケート（原本）」の記載項目に合わせて作成しています。</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    initial: { type: Object, default: () => ({}) },
});

const colorOptions = ['赤', '黄', 'オレンジ', 'ピンク', '青', '水色', '緑', '黒', '紫'];
const hobbyOptions = ['旅行', 'アウトドア', 'スポーツ', '音楽', '映画'];
const priceOptions = ['5万～10万', '11万～20万', '21万～25万', '26万～30万', '31万～40万', '41万～50万', '51万～60万', '61万～80万', '81万以上', '100万以上'];

// 来店動機の選択肢（Event/ReservationForm と同一）
const visitReasonOptions = [
    { value: '紹介', label: '紹介' },
    { value: 'DM・カタログ', label: 'DM・カタログ' },
    { value: 'SNS広告(Instaなど)', label: 'SNS広告(Instaなど)' },
    { value: 'WEB広告', label: 'WEB広告' },
    { value: 'その他', label: 'その他(テキスト入力)' },
];

const initialSisters = Array.isArray(props.initial.sisters) && props.initial.sisters.length > 0
    ? props.initial.sisters.map(s => ({ name: s.name ?? '', year: s.year ?? '', month: s.month ?? '', day: s.day ?? '' }))
    : [{ name: '', year: '', month: '', day: '' }];

const form = useForm({
    entry_date_year: props.initial.entry_date_year ?? '',
    entry_date_month: props.initial.entry_date_month ?? '',
    entry_date_day: props.initial.entry_date_day ?? '',
    name_daughter: props.initial.name_daughter ?? '',
    furigana_daughter: props.initial.furigana_daughter ?? '',
    name_mother: props.initial.name_mother ?? '',
    furigana_mother: props.initial.furigana_mother ?? '',
    birth_year: props.initial.birth_year ?? '',
    birth_month: props.initial.birth_month ?? '',
    birth_day: props.initial.birth_day ?? '',
    height: props.initial.height ?? '',
    foot_size: props.initial.foot_size ?? '',
    postal_code: props.initial.postal_code ?? '',
    address: props.initial.address ?? '',
    phone_home_1: props.initial.phone_home_1 ?? '',
    phone_home_2: props.initial.phone_home_2 ?? '',
    phone_home_3: props.initial.phone_home_3 ?? '',
    phone_daughter_1: props.initial.phone_daughter_1 ?? '',
    phone_daughter_2: props.initial.phone_daughter_2 ?? '',
    phone_daughter_3: props.initial.phone_daughter_3 ?? '',
    phone_mother_1: props.initial.phone_mother_1 ?? '',
    phone_mother_2: props.initial.phone_mother_2 ?? '',
    phone_mother_3: props.initial.phone_mother_3 ?? '',
    color: Array.isArray(props.initial.color) ? [...props.initial.color] : [],
    color_other: props.initial.color_other ?? '',
    hobby: Array.isArray(props.initial.hobby) ? [...props.initial.hobby] : [],
    hobby_other: props.initial.hobby_other ?? '',
    sports_detail: props.initial.sports_detail ?? '',
    furisode_image: props.initial.furisode_image ?? '',
    graduation_year: props.initial.graduation_year ?? '',
    hakama: props.initial.hakama ?? '',
    plan: props.initial.plan ?? '',
    option: Array.isArray(props.initial.option) ? [...props.initial.option] : [],
    price: Array.isArray(props.initial.price) ? [...props.initial.price] : [],
    university: props.initial.university ?? '',
    college: props.initial.college ?? '',
    parttime: props.initial.parttime ?? '',
    work: props.initial.work ?? '',
    other_status: props.initial.other_status ?? '',
    sisters: initialSisters,
    visit_reasons: Array.isArray(props.initial.visit_reasons) ? [...props.initial.visit_reasons] : [],
    visit_reason_other: props.initial.visit_reason_other ?? '',
    staff_name: props.initial.staff_name ?? '',
});

const showColorOther = ref(!!(props.initial.color_other ?? '').trim());
const showHobbyOther = ref(!!(props.initial.hobby_other ?? '').trim());

function addSister() {
    form.sisters.push({ name: '', year: '', month: '', day: '' });
}

function removeSister(index) {
    form.sisters.splice(index, 1);
}

function submit() {
    form.transform((data) => {
        const { processing, color_other, hobby_other, ...rest } = data;
        const color = [...(rest.color || []), (color_other || '').trim()].filter(Boolean);
        const hobby = [...(rest.hobby || []), (hobby_other || '').trim()].filter(Boolean);
        return {
            additional_info: {
                ...rest,
                color,
                hobby,
            },
        };
    }).put(route('admin.customers.additional-info.store', props.customer.id));
}
</script>
