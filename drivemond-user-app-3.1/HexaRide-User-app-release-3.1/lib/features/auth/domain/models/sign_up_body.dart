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
  String? identificationNumber;
  String? referralCode;
  String? qrToken;



  SignUpBody({this.username, this.fName, this.lName, this.phone, this.email='',
    this.password, this.confirmPassword, this.referralCode, this.qrToken});

  SignUpBody.fromJson(Map<String, dynamic> json) {
    username = json['username'];
    fName = json['first_name'];
    lName = json['last_name'];
    phone = json['phone'];
    password = json['pin'];
    confirmPassword = json['pin_confirmation'];
    referralCode = json['referral_code'];


  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['username'] = username;
    data['first_name'] = fName;
    data['last_name'] = lName;
    if (phone != null) data['phone'] = phone;
    data['pin'] = password;
    data['pin_confirmation'] = confirmPassword;
    data['referral_code'] = referralCode;
    if (qrToken != null) data['qr_token'] = qrToken;
    return data;
  }
}
