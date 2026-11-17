<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        if (Page::where('slug', 'blog')->doesntExist()) {
            Page::create([
                'title' => 'Blog',
                'slug' => 'blog',
                'content' => '<p>Welcome to our blog!</p><p>[split]</p><p>You can add more content to the bottom of the list.</p>',
                'is_published' => true,
            ]);
        }

        $disclaimerContent = '
<div class="space-y-6 text-gray-800">
    <div class="border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-900">ĐIỀU KHOẢN SỬ DỤNG VÀ CHÍNH SÁCH TỪ CHỐI TRÁCH NHIỆM</h1>
        <p class="text-sm text-gray-500 mt-1">Hệ thống thư điện tử tạm thời IMAIL (imail.edu.vn) • Cập nhật lần cuối: Tháng 11/2026</p>
    </div>

    <section class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-md">
        <h2 class="text-lg font-bold text-blue-900 mb-2">1. ĐƠN VỊ DUY TRÌ VÀ VẬN HÀNH HỆ THỐNG</h2>
        <ul class="space-y-1.5 text-sm">
            <li><strong>Đơn vị chủ quản:</strong> CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB</li>
            <li><strong>Mã số doanh nghiệp (MST):</strong> 3604049476</li>
            <li><strong>Địa chỉ trụ sở:</strong> 59 Đặng Đại Độ, Phường Trấn Biên, Thành phố Đồng Nai</li>
            <li><strong>Hotline hỗ trợ:</strong> <a href="tel:0845656869" class="text-blue-600 font-semibold hover:underline">0845656869</a> - <a href="tel:0888866548" class="text-blue-600 font-semibold hover:underline">0888866548</a></li>
            <li><strong>Email liên hệ / Khiếu nại:</strong> <a href="mailto:contact@softlab.vn" class="text-blue-600 font-semibold hover:underline">contact@softlab.vn</a></li>
            <li><strong>Kho lưu trữ mã nguồn mở:</strong> <a href="https://github.com/SoftLabVN/tmail-dockerized" target="_blank" rel="noopener noreferrer" class="text-blue-600 font-semibold hover:underline">https://github.com/SoftLabVN/tmail-dockerized</a></li>
        </ul>
    </section>

    <section>
        <h2 class="text-lg font-bold text-gray-900 mb-2">2. MỤC ĐÍCH CUNG CẤP DỊCH VỤ</h2>
        <p class="text-sm leading-relaxed text-gray-700">
            Hệ thống <strong>IMAIL (imail.edu.vn)</strong> là nền tảng cung cấp dịch vụ thư điện tử tạm thời (disposable/temporary email), hoạt động tự động và hoàn toàn miễn phí. Dịch vụ được phát triển với mục đích phục vụ cộng đồng nhằm:
        </p>
        <ul class="list-disc list-inside text-sm mt-2 space-y-1 text-gray-700">
            <li>Bảo vệ quyền riêng tư cá nhân khi tham gia các diễn đàn, dịch vụ mạng công cộng.</li>
            <li>Ngăn chặn và hạn chế thư rác (spam mail), thư quảng cáo không mong muốn vào hòm thư cá nhân chính thức.</li>
            <li>Phục vụ các hoạt động học tập, nghiên cứu công nghệ, lập trình và kiểm thử phần mềm (QA / software testing).</li>
        </ul>
    </section>

    <section class="bg-red-50 border-l-4 border-red-600 p-4 rounded-r-md">
        <h2 class="text-lg font-bold text-red-900 mb-2">3. CÁC HÀNH VI BỊ NGHIÊM CẤM TUYỆT ĐỐI</h2>
        <p class="text-sm text-gray-700 mb-2">
            Người sử dụng <strong>tuyệt đối không được phép</strong> sử dụng địa chỉ email do hệ thống IMAIL cung cấp cho bất kỳ mục đích nào sau đây:
        </p>
        <ol class="list-decimal list-inside text-sm space-y-2 text-gray-800">
            <li><strong>Lừa đảo, gian lận:</strong> Tạo tài khoản giả mạo, lừa đảo chiếm đoạt tài sản, phát tán thông tin sai sự thật, mạo danh cơ quan nhà nước, tổ chức hoặc cá nhân khác.</li>
            <li><strong>Phát tán mã độc & Tấn công mạng:</strong> Gửi hoặc nhận mã độc, virus, trojan, phần mềm gián điệp; thực hiện hành vi tấn công mạng (DDoS, phishing, spam bot) hoặc phá hoại hệ sinh thái mạng theo Luật An ninh mạng Việt Nam.</li>
            <li><strong>Quấy rối, đe dọa, vi phạm an ninh:</strong> Phát tán nội dung khiêu dâm, đồi trụy, quấy rối, xúc phạm danh dự nhân phẩm người khác; tuyên truyền chống phá Nhà nước, vi phạm thuần phong mỹ tục hoặc pháp luật hiện hành.</li>
            <li><strong>Rửa tiền, tài trợ bất hợp pháp:</strong> Lợi dụng hộp thư để thực hiện các giao dịch rửa tiền, cờ bạc trực tuyến, gian lận tài chính hoặc các hoạt động phi pháp.</li>
            <li><strong>Sử dụng cho dữ liệu nhạy cảm:</strong> Tuyệt đối không dùng để nhận mã xác thực ngân hàng (OTP tài khoản thanh toán, ví điện tử), thông tin bảo mật có giá trị cao vì đây là hòm thư tạm thời công cộng.</li>
        </ol>
    </section>

    <section>
        <h2 class="text-lg font-bold text-gray-900 mb-2">4. TUYÊN BỐ TỪ CHỐI VÀ MIỄN TRỪ TRÁCH NHIỆM (DISCLAIMER)</h2>
        <div class="space-y-3 text-sm text-gray-700">
            <p>
                <strong>4.1. Miễn trừ về nội dung thư từ:</strong> Hệ thống tiếp nhận thư tự động từ internet. Đơn vị vận hành <em>(CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB)</em> hoàn toàn không kiểm duyệt trước nội dung, không kiểm soát và không chịu bất kỳ trách nhiệm pháp lý, dân sự hoặc hình sự nào đối với các nội dung do bên thứ ba gửi đến các hộp thư tạm.
            </p>
            <p>
                <strong>4.2. Trách nhiệm hoàn toàn thuộc về người dùng:</strong> Người sử dụng tự chịu 100% trách nhiệm trước pháp luật đối với mọi hành vi, quyết định và hậu quả phát sinh từ việc sử dụng hòm thư. Bất kỳ cá nhân hoặc tổ chức nào lợi dụng hệ thống để vi phạm pháp luật sẽ phải tự chịu trách nhiệm và bị xử lý theo chế tài của pháp luật Việt Nam.
            </p>
            <p>
                <strong>4.3. Miễn trừ về tính khả dụng và dữ liệu:</strong> Dịch vụ được cung cấp theo nguyên tắc "nguyên trạng" (As Is) và "sẵn có" (As Available). Do tính chất email tạm, các hộp thư và nội dung thư có thể tự động bị xóa, làm mới hoặc hủy bỏ bất kỳ lúc nào mà không cần báo trước. Chúng tôi không cam kết lưu trữ vĩnh viễn và không chịu trách nhiệm đối với bất kỳ thiệt hại nào do mất mát dữ liệu hoặc gián đoạn dịch vụ.
            </p>
        </div>
    </section>

    <section>
        <h2 class="text-lg font-bold text-gray-900 mb-2">5. QUYỀN HẠN VẬN HÀNH VÀ PHỐI HỢP CƠ QUAN CHỨC NĂNG</h2>
        <div class="space-y-2 text-sm text-gray-700">
            <p>
                Đơn vị vận hành bảo lưu quyền thực hiện các biện pháp kỹ thuật cần thiết bất cứ lúc nào mà không cần thông báo trước, bao gồm:
            </p>
            <ul class="list-disc list-inside space-y-1">
                <li>Chặn địa chỉ IP, dải IP hoặc domain có dấu hiệu tấn công, spam hoặc lạm dụng.</li>
                <li>Xóa bỏ hộp thư, loại bỏ tin nhắn vi phạm chính sách an toàn.</li>
                <li><strong>Hợp tác điều tra:</strong> Đơn vị vận hành cam kết tuân thủ pháp luật và sẵn sàng trích xuất, cung cấp đầy đủ thông tin kỹ thuật, nhật ký hệ thống (access logs, network logs, email headers) cho các Cơ quan An ninh mạng, Cơ quan Công an và Cơ quan Nhà nước có thẩm quyền theo quy định của pháp luật khi có yêu cầu bằng văn bản phục vụ công tác điều tra xử lý tội phạm.</li>
            </ul>
        </div>
    </section>

    <section class="border-t pt-4">
        <h2 class="text-lg font-bold text-gray-900 mb-2">6. BÁO CÁO VI PHẠM & LIÊN HỆ KHIẾU NẠI</h2>
        <p class="text-sm text-gray-700">
            Nếu bạn phát hiện bất kỳ hộp thư hoặc hành vi nào có dấu hiệu lạm dụng, vi phạm bản quyền hoặc vi phạm pháp luật liên quan đến hệ thống IMAIL, vui lòng gửi phản ánh ngay đến ban quản trị:
        </p>
        <div class="mt-2 text-sm bg-gray-100 p-3 rounded space-y-1">
            <p><strong>Ban Quản trị & Hỗ trợ kỹ thuật:</strong> CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB</p>
            <p><strong>Email tiếp nhận Abuse / Khiếu nại:</strong> <a href="mailto:contact@softlab.vn" class="text-blue-600 font-semibold hover:underline">contact@softlab.vn</a></p>
            <p><strong>Hotline:</strong> 0845656869 - 0888866548</p>
            <p class="text-gray-500 text-xs">Chúng tôi sẽ tiếp nhận, xác minh và xử lý/phối hợp trong thời gian sớm nhất.</p>
        </div>
    </section>
</div>
';

        Page::updateOrCreate(
            ['slug' => 'dieu-khoan-va-tu-choi-trach-nhiem'],
            [
                'title' => 'Điều khoản & Từ chối trách nhiệm',
                'content' => $disclaimerContent,
                'is_published' => true,
            ]
        );
    }
}
