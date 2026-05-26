class SignUpBody {
  String? username;
  String? fName;
  String? lName;
  String? phone;
  String? email;
  String? password;
  String? confirmPassword;
  String? address;
  String? identificationType;
  String? identityNumber;
  String? referralCode;
  List<String>? services;
  String? fcmToken;
  String? qrToken;

  SignUpBody({this.username,
    this.fName,
    this.lName,
    this.phone,
    this.email,
    this.password,
    this.confirmPassword,
    this.address,
    this.identificationType,
    this.identityNumber,
    this.services,
    this.referralCode,
    this.fcmToken,
    this.qrToken,
  });

  SignUpBody.fromJson(Map<String, dynamic> json) {
    username = json['username'];
    fName = json['first_name'];
    lName = json['last_name'];
    phone = json['phone'];
    password = json['pin'];
    confirmPassword = json['pin_confirmation'];
    email = json['email'];
    address = json['address'];
    identificationType = json['identification_type'];
    identityNumber = json['identification_number'];
    referralCode = json['referral_code'];
    fcmToken = json['fcm_token'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['username'] = username ?? '';
    data['first_name'] = fName ?? '';
    data['last_name'] = lName ?? '';
    data['pin'] = password ?? '';
    data['pin_confirmation'] = confirmPassword ?? '';
    data['email'] = email ?? '';
    data['referral_code'] = referralCode ?? '';
    if (qrToken != null && qrToken!.isNotEmpty) {
      data['qr_token'] = qrToken!;
    }
    return data;
  }
}
