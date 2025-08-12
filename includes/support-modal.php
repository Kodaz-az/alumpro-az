<!-- Support Modal -->
<div class="modal fade" id="supportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-headset"></i> Dəstək Xidməti
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="supportChat" style="height: 400px; overflow-y: auto; padding: 1rem;">
                    <div class="chat-message bot">
                        <div class="chat-bubble bot">
                            <i class="bi bi-robot"></i> Salam! Alumpro.Az dəstək xidmətinə xoş gəlmisiniz. Sizə necə kömək edə bilərəm?
                        </div>
                        <small class="text-muted"><?= date('H:i') ?></small>
                    </div>
                </div>
                <div class="border-top p-3">
                    <div class="input-group mb-2">
                        <button class="btn btn-outline-secondary" type="button" id="voiceButton" title="Səs yazma">
                            <i class="bi bi-mic"></i>
                        </button>
                        <input type="text" class="form-control" id="supportMessage" 
                               placeholder="Mesajınızı yazın..." maxlength="500">
                        <button class="btn btn-primary" type="button" id="sendSupportMessage">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                    <div>
                        <small class="text-muted">Tez suallar:</small>
                        <div id="quickQuestions" class="mt-1">
                            <a href="#" class="quick-question" data-question="Qiymətlər haqqında məlumat istəyirəm">Qiymətlər</a>
                            <a href="#" class="quick-question" data-question="Çatdırılma xidməti var?">Çatdırılma</a>
                            <a href="#" class="quick-question" data-question="Sifariş necə verim?">Sifariş</a>
                            <a href="#" class="quick-question" data-question="Ödəniş üsulları">Ödəniş</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>