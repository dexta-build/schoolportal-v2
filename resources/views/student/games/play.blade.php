<x-layouts::app :title="$game->title">
    <div class="space-y-4" x-data="gamePlayer()">
        {{-- Header --}}
        <div>
            <flux:button variant="subtle" size="sm" href="{{ route('student.games.index') }}" wire:navigate class="mb-2">
                <flux:icon name="arrow-left" class="size-4 mr-1" /> {{ __('Back to Games') }}
            </flux:button>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $game->title }}</h1>
            <p class="text-sm text-zinc-500 mt-1">{{ $game->gameTypeLabel() }} &middot; {{ ucfirst($game->difficulty) }}</p>
        </div>

        {{-- Game Area --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6">
            {{-- Pre-game screen --}}
            <template x-if="state === 'ready'">
                <div class="text-center py-8">
                    <flux:icon name="play-circle" class="mx-auto h-16 w-16 text-indigo-500" />
                    <h2 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Ready to Play?') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ $game->gameTypeLabel() }} &middot; {{ ucfirst($game->difficulty) }}</p>
                    @if ($bestPlay)
                        <p class="mt-2 text-sm text-zinc-500">{{ __('Your best score:') }} {{ number_format($bestPlay->percentage, 0) }}%</p>
                    @endif
                    <div class="mt-6">
                        <flux:button variant="primary" @click="startGame()">{{ __('Start Game') }}</flux:button>
                    </div>
                </div>
            </template>

            {{-- Memory Match --}}
            @if ($game->game_type === 'memory_match')
                <template x-if="state === 'playing'">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm text-zinc-500">
                                {{ __('Pairs:') }} <span class="font-semibold text-zinc-900 dark:text-white" x-text="matchedPairs + ' / ' + totalPairs"></span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-zinc-500">
                                <span>{{ __('Moves:') }} <span class="font-semibold" x-text="moves"></span></span>
                                <span x-text="formatTime(elapsed)"></span>
                            </div>
                        </div>
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 mb-4">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="'width:' + (matchedPairs / totalPairs * 100) + '%'"></div>
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 sm:gap-3">
                            <template x-for="(card, i) in cards" :key="i">
                                <button type="button"
                                    @click="flipCard(i)"
                                    :disabled="card.matched || flipped.length >= 2"
                                    class="aspect-square rounded-lg border-2 text-xs sm:text-sm font-medium p-2 transition-all duration-200 flex items-center justify-center text-center leading-tight"
                                    :class="card.matched ? 'bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-700 text-green-700 dark:text-green-300' : (card.flipped ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-300 dark:border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'bg-zinc-100 dark:bg-zinc-700 border-zinc-300 dark:border-zinc-600 text-zinc-400 hover:border-indigo-400 cursor-pointer')">
                                    <span x-show="card.flipped || card.matched" x-text="card.text" x-transition></span>
                                    <span x-show="!card.flipped && !card.matched">?</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            @endif

            {{-- Word Scramble --}}
            @if ($game->game_type === 'word_scramble')
                <template x-if="state === 'playing'">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm text-zinc-500">
                                {{ __('Word:') }} <span class="font-semibold text-zinc-900 dark:text-white" x-text="(currentWordIndex + 1) + ' / ' + words.length"></span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-zinc-500">
                                <span>{{ __('Score:') }} <span class="font-semibold" x-text="score"></span></span>
                                <span x-text="formatTime(elapsed)"></span>
                            </div>
                        </div>
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 mb-6">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="'width:' + ((currentWordIndex) / words.length * 100) + '%'"></div>
                        </div>
                        <div class="text-center py-4">
                            <p class="text-3xl sm:text-4xl font-bold tracking-widest text-indigo-600 dark:text-indigo-400 font-mono mb-4" x-text="scrambled"></p>
                            <p class="text-sm text-zinc-500 mb-4" x-show="showHint">
                                {{ __('Hint:') }} <span x-text="words[currentWordIndex]?.hint"></span>
                            </p>
                            <div class="max-w-xs mx-auto mb-4">
                                <input type="text" x-model="guess"
                                    @keydown.enter="checkWord()"
                                    class="w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-3 py-2 text-center text-lg text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500"
                                    :placeholder="'{{ __('Type the word...') }}'"
                                    x-ref="wordInput">
                            </div>
                            <div class="flex items-center justify-center gap-3">
                                <flux:button variant="primary" @click="checkWord()">{{ __('Check') }}</flux:button>
                                <flux:button variant="subtle" @click="showHint = true" x-show="!showHint && hintsLeft > 0">
                                    {{ __('Hint') }} (<span x-text="hintsLeft"></span>)
                                </flux:button>
                                <flux:button variant="subtle" @click="skipWord()">{{ __('Skip') }}</flux:button>
                            </div>
                            <p class="mt-3 text-sm font-medium" x-show="feedback" x-text="feedback"
                                :class="feedbackCorrect ? 'text-green-600' : 'text-red-600'" x-transition></p>
                        </div>
                    </div>
                </template>
            @endif

            {{-- Quiz Race --}}
            @if ($game->game_type === 'quiz_race')
                <template x-if="state === 'playing'">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm text-zinc-500">
                                {{ __('Question:') }} <span class="font-semibold text-zinc-900 dark:text-white" x-text="(currentQIndex + 1) + ' / ' + questions.length"></span>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                <span class="text-zinc-500">{{ __('Score:') }} <span class="font-semibold" x-text="score"></span></span>
                                <span class="font-bold" :class="questionTimer <= 3 ? 'text-red-600' : 'text-zinc-500'" x-text="questionTimer + 's'"></span>
                            </div>
                        </div>
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 mb-4">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="'width:' + (questionTimer / 10 * 100) + '%'"></div>
                        </div>
                        <div x-show="streak >= 3" class="text-center mb-2">
                            <span class="text-sm font-semibold text-amber-500">{{ __('Streak:') }} <span x-text="streak"></span> &mdash; {{ __('Bonus points!') }}</span>
                        </div>
                        <div class="text-center py-4">
                            <p class="text-lg font-semibold text-zinc-900 dark:text-white mb-6" x-text="questions[currentQIndex]?.question"></p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-lg mx-auto">
                                <template x-for="(opt, oi) in shuffledOptions" :key="oi">
                                    <button type="button"
                                        @click="answerQuestion(opt)"
                                        :disabled="answered"
                                        class="rounded-lg border-2 p-3 text-sm font-medium transition-all"
                                        :class="getOptionClass(opt)">
                                        <span x-text="opt"></span>
                                    </button>
                                </template>
                            </div>
                            <p class="mt-4 text-sm font-medium" x-show="answered" x-text="answerFeedback"
                                :class="lastAnswerCorrect ? 'text-green-600' : 'text-red-600'" x-transition></p>
                        </div>
                    </div>
                </template>
            @endif

            {{-- Flashcard --}}
            @if ($game->game_type === 'flashcard')
                <template x-if="state === 'playing'">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm text-zinc-500">
                                {{ __('Card:') }} <span class="font-semibold text-zinc-900 dark:text-white" x-text="(currentCardIndex + 1) + ' / ' + flashcards.length"></span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-zinc-500">
                                <span>{{ __('Got it:') }} <span class="font-semibold text-green-600" x-text="gotIt"></span></span>
                                <span>{{ __('Review:') }} <span class="font-semibold text-amber-600" x-text="reviewAgain"></span></span>
                            </div>
                        </div>
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 mb-6">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="'width:' + ((currentCardIndex) / flashcards.length * 100) + '%'"></div>
                        </div>
                        <div class="flex justify-center py-4">
                            <div @click="cardFlipped = !cardFlipped"
                                class="w-full max-w-md aspect-[3/2] rounded-xl border-2 border-zinc-300 dark:border-zinc-600 cursor-pointer flex items-center justify-center p-6 transition-all duration-300"
                                :class="cardFlipped ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-300 dark:border-indigo-600' : 'bg-white dark:bg-zinc-800'">
                                <div class="text-center">
                                    <p class="text-xs uppercase tracking-wide text-zinc-400 mb-2" x-text="cardFlipped ? '{{ __("Answer") }}' : '{{ __("Question") }}'"></p>
                                    <p class="text-lg font-semibold text-zinc-900 dark:text-white" x-text="cardFlipped ? flashcards[currentCardIndex]?.back : flashcards[currentCardIndex]?.front"></p>
                                    <p class="text-xs text-zinc-400 mt-4" x-show="!cardFlipped">{{ __('Tap to reveal answer') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center gap-3 mt-4" x-show="cardFlipped">
                            <flux:button variant="subtle" @click="markCard('review')" class="text-amber-600">
                                <flux:icon name="arrow-path" class="size-4 mr-1" /> {{ __('Review Again') }}
                            </flux:button>
                            <flux:button variant="primary" @click="markCard('got_it')">
                                <flux:icon name="check" class="size-4 mr-1" /> {{ __('Got It!') }}
                            </flux:button>
                        </div>
                    </div>
                </template>
            @endif

            {{-- Completed screen --}}
            <template x-if="state === 'completed'">
                <div class="text-center py-8">
                    <div class="text-4xl font-bold text-indigo-600 dark:text-indigo-400" x-text="Math.round(finalPercentage) + '%'"></div>
                    <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Game Complete!') }}</p>
                    <p class="mt-1 text-sm text-zinc-500">
                        {{ __('Score:') }} <span x-text="finalScore + ' / ' + finalMaxScore"></span>
                        &middot; {{ __('Time:') }} <span x-text="formatTime(elapsed)"></span>
                    </p>
                    <div class="w-48 mx-auto mt-4 bg-zinc-200 dark:bg-zinc-700 rounded-full h-3">
                        <div class="h-3 rounded-full transition-all"
                            :class="finalPercentage >= 70 ? 'bg-green-500' : (finalPercentage >= 40 ? 'bg-amber-500' : 'bg-red-500')"
                            :style="'width:' + finalPercentage + '%'"></div>
                    </div>

                    {{-- Score not yet confirmed by the server --}}
                    <div x-show="offlineScoreQueued || sessionExpired" role="status" aria-live="polite" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">
                        <svg class="size-4 shrink-0 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83m5.145 5.145A9.955 9.955 0 0 1 12 22C6.477 22 2 17.523 2 12c0-2.106.654-4.062 1.77-5.672m3.144-2.65A9.956 9.956 0 0 1 12 2c5.523 0 10 4.477 10 10 0 2.107-.655 4.063-1.77 5.673"/></svg>
                        <span x-show="sessionExpired">{{ __('Session expired — your score is kept on this device and will be saved after you log in again.') }}</span>
                        <span x-show="!sessionExpired">{{ __("Your score hasn't been saved yet — retrying automatically.") }}</span>
                    </div>

                    <div class="mt-6 flex items-center justify-center gap-3">
                        <flux:button variant="primary" @click="resetGame()">{{ __('Play Again') }}</flux:button>
                        <flux:button variant="subtle" href="{{ route('student.games.index') }}" wire:navigate>{{ __('Back to Games') }}</flux:button>
                        @if ($game->game_type === 'quiz_race')
                            <flux:button variant="subtle" href="{{ route('student.games.leaderboard', $game) }}" wire:navigate>{{ __('Leaderboard') }}</flux:button>
                        @endif
                    </div>
                </div>
            </template>
        </div>
    </div>

    @push('scripts')
    <script>
        function gamePlayer() {
            const gameType = '{{ $game->game_type }}';
            const gameData = @json($game->game_data);
            const gameId = {{ $game->id }};
            const completeUrl = '{{ route("student.games.complete", $game) }}';
            const csrfToken = '{{ csrf_token() }}';
            const scoreQueueKey = 'game_score_queue_' + gameId;

            return {
                state: 'ready',
                elapsed: 0,
                timer: null,
                score: 0,
                finalScore: 0,
                finalMaxScore: 0,
                finalPercentage: 0,
                offlineScoreQueued: false,
                sessionExpired: false,
                retryTimer: null,
                retryDelay: 2000,
                _flushing: false,

                // Memory Match
                cards: [],
                flipped: [],
                matchedPairs: 0,
                totalPairs: 0,
                moves: 0,

                // Word Scramble
                words: [],
                currentWordIndex: 0,
                scrambled: '',
                guess: '',
                showHint: false,
                hintsLeft: 3,
                feedback: '',
                feedbackCorrect: false,

                // Quiz Race
                questions: [],
                currentQIndex: 0,
                questionTimer: 10,
                questionInterval: null,
                streak: 0,
                answered: false,
                answerFeedback: '',
                lastAnswerCorrect: false,
                shuffledOptions: [],
                selectedOpt: null,

                // Flashcard
                flashcards: [],
                currentCardIndex: 0,
                cardFlipped: false,
                gotIt: 0,
                reviewAgain: 0,

                formatTime(s) {
                    const m = Math.floor(s / 60);
                    const sec = s % 60;
                    return m + ':' + String(sec).padStart(2, '0');
                },

                init() {
                    this._onlineHandler = () => this.flushScores();
                    window.addEventListener('online', this._onlineHandler);

                    // Send scores queued in an earlier visit (finished offline, or the server was down)
                    this.offlineScoreQueued = this._readQueue().length > 0;
                    this.flushScores();
                },

                startGame() {
                    this.state = 'playing';
                    this.elapsed = 0;
                    this.score = 0;
                    this.timer = setInterval(() => this.elapsed++, 1000);

                    if (gameType === 'memory_match') this.initMemoryMatch();
                    else if (gameType === 'word_scramble') this.initWordScramble();
                    else if (gameType === 'quiz_race') this.initQuizRace();
                    else if (gameType === 'flashcard') this.initFlashcard();
                },

                resetGame() {
                    this.state = 'ready';
                    clearInterval(this.timer);
                    clearInterval(this.questionInterval);
                },

                completeGame(score, maxScore) {
                    clearInterval(this.timer);
                    clearInterval(this.questionInterval);
                    this.finalScore = score;
                    this.finalMaxScore = maxScore;
                    this.finalPercentage = maxScore > 0 ? (score / maxScore * 100) : 0;
                    this.state = 'completed';

                    // Queue first, then send — the score is only removed once the server confirms it.
                    // The submission ID lets the server ignore a retry of a play it already recorded.
                    const queue = this._readQueue();
                    queue.push({
                        submission_id: window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`,
                        score,
                        max_score: maxScore,
                        time_spent_seconds: this.elapsed,
                    });
                    this._writeQueue(queue);
                    this.offlineScoreQueued = true;
                    this.flushScores();
                },

                _readQueue() {
                    try {
                        const queued = JSON.parse(localStorage.getItem(scoreQueueKey) || '[]');
                        // Older versions stored a single score object
                        return Array.isArray(queued) ? queued : (queued ? [queued] : []);
                    } catch (e) {
                        return [];
                    }
                },

                _writeQueue(queue) {
                    try {
                        if (queue.length) localStorage.setItem(scoreQueueKey, JSON.stringify(queue));
                        else localStorage.removeItem(scoreQueueKey);
                    } catch (e) {}
                },

                // Sends queued scores one at a time. Failures (offline, server errors) are retried
                // with backoff until the server confirms, so no score is silently dropped.
                async flushScores() {
                    if (this._flushing || this.sessionExpired) return;
                    this._flushing = true;

                    try {
                        for (const entry of this._readQueue()) {
                            if (!navigator.onLine) throw new Error('offline');

                            const response = await fetch(completeUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                body: JSON.stringify(entry),
                            });

                            if (response.status === 419 || response.status === 401) {
                                // Session ended — the score stays queued and is sent after the next login
                                this.sessionExpired = true;
                                return;
                            }

                            // 403/422: game closed or invalid score — retrying won't help
                            if (!response.ok && response.status !== 403 && response.status !== 422) {
                                throw new Error('Save failed with status ' + response.status);
                            }

                            this._writeQueue(this._readQueue().filter(q => q.submission_id !== entry.submission_id));
                        }

                        this.retryDelay = 2000;
                        this.offlineScoreQueued = false;
                    } catch (e) {
                        this.offlineScoreQueued = true;
                        clearTimeout(this.retryTimer);
                        this.retryTimer = setTimeout(() => this.flushScores(), this.retryDelay);
                        this.retryDelay = Math.min(this.retryDelay * 2, 30000);
                    } finally {
                        this._flushing = false;
                    }
                },

                destroy() {
                    clearInterval(this.timer);
                    clearInterval(this.questionInterval);
                    clearTimeout(this.retryTimer);
                    if (this._onlineHandler) window.removeEventListener('online', this._onlineHandler);
                },

                // ── Memory Match ──
                initMemoryMatch() {
                    const pairs = gameData.pairs || [];
                    this.totalPairs = pairs.length;
                    this.matchedPairs = 0;
                    this.moves = 0;
                    this.flipped = [];
                    let allCards = [];
                    pairs.forEach((p, idx) => {
                        allCards.push({ text: p.term, pairId: idx, type: 'term', flipped: false, matched: false });
                        allCards.push({ text: p.definition, pairId: idx, type: 'def', flipped: false, matched: false });
                    });
                    this.cards = this.shuffle(allCards);
                },

                flipCard(i) {
                    if (this.cards[i].flipped || this.cards[i].matched || this.flipped.length >= 2) return;
                    this.cards[i].flipped = true;
                    this.flipped.push(i);

                    if (this.flipped.length === 2) {
                        this.moves++;
                        const a = this.cards[this.flipped[0]];
                        const b = this.cards[this.flipped[1]];
                        if (a.pairId === b.pairId && a.type !== b.type) {
                            a.matched = true;
                            b.matched = true;
                            this.matchedPairs++;
                            this.flipped = [];
                            if (this.matchedPairs === this.totalPairs) {
                                const maxMoves = this.totalPairs * 3;
                                const sc = Math.max(0, Math.round((1 - (this.moves - this.totalPairs) / maxMoves) * this.totalPairs * 10));
                                this.completeGame(sc, this.totalPairs * 10);
                            }
                        } else {
                            setTimeout(() => {
                                this.cards[this.flipped[0]].flipped = false;
                                this.cards[this.flipped[1]].flipped = false;
                                this.flipped = [];
                            }, 800);
                        }
                    }
                },

                // ── Word Scramble ──
                initWordScramble() {
                    this.words = (gameData.words || []).map(w => ({ word: w.word.toUpperCase(), hint: w.hint }));
                    this.currentWordIndex = 0;
                    this.hintsLeft = 3;
                    this.score = 0;
                    this.setScrambled();
                },

                setScrambled() {
                    if (this.currentWordIndex >= this.words.length) {
                        this.completeGame(this.score, this.words.length);
                        return;
                    }
                    const word = this.words[this.currentWordIndex].word;
                    this.scrambled = this.shuffle(word.split('')).join('');
                    if (this.scrambled === word && word.length > 1) this.scrambled = this.shuffle(word.split('')).join('');
                    this.guess = '';
                    this.showHint = false;
                    this.feedback = '';
                    this.$nextTick(() => { if (this.$refs.wordInput) this.$refs.wordInput.focus(); });
                },

                checkWord() {
                    if (!this.guess.trim()) return;
                    if (this.guess.trim().toUpperCase() === this.words[this.currentWordIndex].word) {
                        this.score++;
                        this.feedback = '{{ __("Correct!") }}';
                        this.feedbackCorrect = true;
                    } else {
                        this.feedback = '{{ __("Wrong! The word was:") }} ' + this.words[this.currentWordIndex].word;
                        this.feedbackCorrect = false;
                    }
                    setTimeout(() => { this.currentWordIndex++; this.setScrambled(); }, 1200);
                },

                skipWord() {
                    this.feedback = '{{ __("Skipped:") }} ' + this.words[this.currentWordIndex].word;
                    this.feedbackCorrect = false;
                    setTimeout(() => { this.currentWordIndex++; this.setScrambled(); }, 1000);
                },

                // ── Quiz Race ──
                initQuizRace() {
                    this.questions = gameData.questions || [];
                    this.currentQIndex = 0;
                    this.score = 0;
                    this.streak = 0;
                    this.setQuestion();
                },

                setQuestion() {
                    if (this.currentQIndex >= this.questions.length) {
                        this.completeGame(this.score, this.questions.length * 100);
                        return;
                    }
                    this.answered = false;
                    this.answerFeedback = '';
                    this.selectedOpt = null;
                    this.questionTimer = 10;
                    this.shuffledOptions = this.shuffle([...this.questions[this.currentQIndex].options]);
                    clearInterval(this.questionInterval);
                    this.questionInterval = setInterval(() => {
                        this.questionTimer--;
                        if (this.questionTimer <= 0) {
                            clearInterval(this.questionInterval);
                            this.answerQuestion(null);
                        }
                    }, 1000);
                },

                answerQuestion(opt) {
                    if (this.answered) return;
                    this.answered = true;
                    this.selectedOpt = opt;
                    clearInterval(this.questionInterval);
                    const correct = this.questions[this.currentQIndex].answer;
                    if (opt === correct) {
                        this.streak++;
                        const bonus = this.streak >= 3 ? 20 : 0;
                        const timeBonus = this.questionTimer * 5;
                        this.score += 50 + timeBonus + bonus;
                        this.lastAnswerCorrect = true;
                        this.answerFeedback = '{{ __("Correct!") }}' + (bonus > 0 ? ' +' + bonus + ' {{ __("streak bonus!") }}' : '');
                    } else {
                        this.streak = 0;
                        this.lastAnswerCorrect = false;
                        this.answerFeedback = opt === null ? '{{ __("Time\'s up!") }} {{ __("Answer:") }} ' + correct : '{{ __("Wrong!") }} {{ __("Answer:") }} ' + correct;
                    }
                    setTimeout(() => { this.currentQIndex++; this.setQuestion(); }, 1500);
                },

                getOptionClass(opt) {
                    if (!this.answered) return 'bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 hover:border-indigo-400 cursor-pointer';
                    const correct = this.questions[this.currentQIndex]?.answer;
                    if (opt === correct) return 'bg-green-100 dark:bg-green-900/30 border-green-500 text-green-700 dark:text-green-300';
                    if (opt === this.selectedOpt) return 'bg-red-100 dark:bg-red-900/30 border-red-500 text-red-700 dark:text-red-300';
                    return 'bg-zinc-100 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 opacity-50';
                },

                // ── Flashcard ──
                initFlashcard() {
                    this.flashcards = gameData.cards || [];
                    this.currentCardIndex = 0;
                    this.cardFlipped = false;
                    this.gotIt = 0;
                    this.reviewAgain = 0;
                },

                markCard(action) {
                    if (action === 'got_it') this.gotIt++;
                    else this.reviewAgain++;
                    this.cardFlipped = false;
                    this.currentCardIndex++;
                    if (this.currentCardIndex >= this.flashcards.length) {
                        this.completeGame(this.gotIt, this.flashcards.length);
                    }
                },

                // Utility
                shuffle(arr) {
                    const a = [...arr];
                    for (let i = a.length - 1; i > 0; i--) {
                        const j = Math.floor(Math.random() * (i + 1));
                        [a[i], a[j]] = [a[j], a[i]];
                    }
                    return a;
                },
            };
        }
    </script>
    @endpush
</x-layouts::app>
