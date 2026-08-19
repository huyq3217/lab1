trả lời câu hỏi chữ đỏ nền vàng
hoạt động 2
1. Sự khác biệt giữa chạy file .py và gõ trực tiếp trong REPL:
Chạy file .py (Script mode): Mã nguồn được lưu trữ vĩnh viễn trên ổ cứng, thực thi toàn bộ script từ trên xuống dưới theo thứ tự; bắt buộc phải dùng hàm print() để xuất kết quả ra màn hình. Thích hợp để xây dựng chương trình hoàn chỉnh.
Chế độ REPL (Interactive mode): Thực thi từng dòng lệnh đơn lẻ ngay khi gõ Enter (Read-Eval-Print); tự động in giá trị biểu thức mà không cần hàm print(). Mã lệnh và biến chỉ lưu tạm trong bộ nhớ của phiên làm việc và sẽ mất hoàn toàn khi đóng cửa sổ. Thích hợp để thử nghiệm nhanh cú pháp hoặc kiểm tra hàm.
2. Khi nào nên dùng Jupyter Notebook thay vì file .py thông thường?
Khi làm việc trong các lĩnh vực Khoa học dữ liệu (Data Science), Trí tuệ nhân tạo (AI/ML), Thống kê và Xử lý dữ liệu
Khi cần trực quan hóa dữ liệu tức thì (vẽ đồ thị, biểu đồ, bảng dữ liệu) ngay bên dưới từng cell code
Khi cần chạy thử/tinh chỉnh từng phần code mà không muốn phải chạy lại toàn bộ chương trình từ đầu (giúp tiết kiệm thời gian xử lý dữ liệu lớn).
Khi cần viết tài liệu, báo cáo nghiên cứu kết hợp giữa văn bản giải thích (Markdown), công thức toán học và mã code thực thi
hoatj động 6
Tại sao cùng một biến có thể mang nhiều kiểu dữ liệu khác nhau trong Python?
Python sử dụng cơ chế định kiểu động (Dynamic Typing).
Trong Python, kiểu dữ liệu gắn liền với đối tượng/giá trị trong bộ nhớ chứ không gắn với tên biến. Tên biến chỉ đơn thuần là một "nhãn dán" (tham chiếu/con trỏ) trỏ tới ô nhớ. Khi gán giá trị mới, nhãn dán đó chỉ việc chuyển sang trỏ tới một đối tượng mới có kiểu dữ liệu khác.
Sự khác biệt so với C/C++/Java (Static Typing):
Trong C/C++/Java (định kiểu tĩnh - Static Typing), khi khai báo int bien = 10;, biến bị ràng buộc cố định với kiểu int ngay từ lúc cấp phát bộ nhớ. Trình biên dịch (Compiler) sẽ báo lỗi ngay lập tức nếu cố tình gán chuỗi hoặc kiểu dữ liệu khác vào biến đó trong suốt vòng đời của chương trình.
