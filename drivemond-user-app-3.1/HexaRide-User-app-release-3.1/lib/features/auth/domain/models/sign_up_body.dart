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



  SignUpBody({this.username, this.fName, this.lName, this.phone, this.email='',
    this.password, this.confirmPassword, this.referralCode});

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
    data['pin'] = password;
    data['pin_confirmation'] = confirmPassword;
    data['referral_code'] = referralCode;
    return data;
  }
}